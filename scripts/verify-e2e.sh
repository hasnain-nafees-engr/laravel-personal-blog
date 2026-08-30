#!/bin/bash
#
# End-to-end verification against the RUNNING application, over real HTTP.
#
#   make up && make fresh && ./scripts/verify-e2e.sh
#
# why this exists alongside `make test`: the Pest suite exercises the
# application in-process, with fakes for the queue, mail and filesystem. This
# script drives the whole stack the way a browser does - through nginx, into
# php-fpm, with the real queue worker and scheduler running.
#
# That difference is not academic. The suite was entirely green while the
# cover-image job called a method that does not exist in Intervention Image
# v4, because the CRUD test faked the queue and never executed the job. Only
# a real upload through this path revealed it.
#
# Exits non-zero if any check fails.
B=${BLOG_URL:-http://localhost:8000}
J=$(mktemp)
PASS=0; FAIL=0
ok()   { printf "  \033[32m✓\033[0m %s\n" "$1"; PASS=$((PASS+1)); }
bad()  { printf "  \033[31m✗\033[0m %s  (%s)\n" "$1" "$2"; FAIL=$((FAIL+1)); }
check(){ [ "$2" = "$3" ] && ok "$1" || bad "$1" "expected $3, got $2"; }
code() { curl -s -o /dev/null -w '%{http_code}' -b $J -c $J "$@"; }
body() { curl -s -b $J -c $J "$@"; }
tok()  { body "$1" | grep -oP 'name="_token"\s+value="\K[^"]+' | head -1; }

section(){ printf "\n\033[1m== %s ==\033[0m\n" "$1"; }

section "1. Public pages (guest)"
check "GET / (home)"                "$(code $B/)" 200
check "GET /posts (index)"          "$(code $B/posts)" 200
check "GET /feed.xml"               "$(code $B/feed.xml)" 200
check "GET /sitemap.xml"            "$(code $B/sitemap.xml)" 200
check "GET /login"                  "$(code $B/login)" 200
check "GET /register"               "$(code $B/register)" 200
check "unknown URL is 404"          "$(code $B/no-such-page)" 404

SLUG=$(body $B/posts | grep -oP '/posts/\K[a-z0-9-]+(?=")' | head -1)
[ -n "$SLUG" ] && ok "found a published post: $SLUG" || bad "find a published post" "none"
check "GET /posts/{slug}"           "$(code $B/posts/$SLUG)" 200

CAT=$(body $B/posts/$SLUG | grep -oP '/categories/\K[a-z0-9-]+(?=")' | head -1)
[ -n "$CAT" ] && check "GET /categories/{slug}" "$(code $B/categories/$CAT)" 200
TAG=$(body $B/posts/$SLUG | grep -oP '/tags/\K[a-z0-9-]+(?=")' | head -1)
[ -n "$TAG" ] && check "GET /tags/{slug}"       "$(code $B/tags/$TAG)" 200

section "2. Public page content"
P=$(body $B/posts/$SLUG)
echo "$P" | grep -q 'prose-blog'            && ok "article body rendered"      || bad "article body" "no prose-blog"
echo "$P" | grep -q 'min read'              && ok "reading time shown"          || bad "reading time" "missing"
echo "$P" | grep -q 'Join the conversation' && ok "comment form present"        || bad "comment form" "missing"
echo "$P" | grep -q 'name="_token"'         && ok "CSRF token in form"          || bad "CSRF token" "missing"
echo "$P" | grep -q 'og:title'              && ok "Open Graph tags"             || bad "OG tags" "missing"
echo "$P" | grep -q 'rel="canonical"'       && ok "canonical URL"               || bad "canonical" "missing"
echo "$P" | grep -q 'Related reading'       && ok "related posts section"       || bad "related posts" "missing"

section "3. Security headers"
H=$(curl -sI $B/)
echo "$H" | grep -qi 'x-content-type-options: nosniff' && ok "X-Content-Type-Options" || bad "X-Content-Type-Options" "missing"
echo "$H" | grep -qi 'x-frame-options: DENY'           && ok "X-Frame-Options"         || bad "X-Frame-Options" "missing"
echo "$H" | grep -qi 'referrer-policy'                 && ok "Referrer-Policy"         || bad "Referrer-Policy" "missing"
echo "$H" | grep -qi 'strict-transport-security'       && bad "HSTS absent over http" "present" || ok "HSTS correctly absent over http"
echo "$H" | grep -qi 'x-powered-by'                    && bad "X-Powered-By hidden" "leaked"    || ok "X-Powered-By hidden"

section "4. Draft and scheduled posts are invisible"
DRAFT=$(docker compose exec -T app php artisan tinker --execute='echo App\Models\Post::draft()->first()?->slug;' 2>/dev/null | tr -d '\r\n')
SCHED=$(docker compose exec -T app php artisan tinker --execute='echo App\Models\Post::scheduled()->first()?->slug;' 2>/dev/null | tr -d '\r\n')
check "draft post is 404"           "$(code $B/posts/$DRAFT)" 404
check "scheduled post is 404"       "$(code $B/posts/$SCHED)" 404
check "draft is 404 in the API"     "$(code $B/api/posts/$DRAFT)" 404

section "5. Read-only JSON API"
check "GET /api/posts"              "$(code $B/api/posts)" 200
A=$(body $B/api/posts)
echo "$A" | grep -q '"data"'  && ok "has data array"        || bad "data array" "missing"
echo "$A" | grep -q '"meta"'  && ok "has pagination meta"   || bad "meta" "missing"
echo "$A" | grep -q 'body_html' && bad "body omitted from list" "present" || ok "body omitted from list endpoint"
check "GET /api/posts/{slug}"       "$(code $B/api/posts/$SLUG)" 200
body $B/api/posts/$SLUG | grep -q 'body_html' && ok "body present on single post" || bad "body_html" "missing"
check "unauthenticated /api/user"   "$(code $B/api/user)" 401

section "6. Admin area is protected (guest)"
check "GET /admin redirects"        "$(code $B/admin)" 302
check "GET /admin/posts redirects"  "$(code $B/admin/posts)" 302
check "POST delete redirects"       "$(curl -s -o /dev/null -w '%{http_code}' -X POST $B/admin/posts/1 -d '_method=DELETE')" 419

section "7. Log in as admin"
rm -f $J; T=$(tok $B/login)
curl -s -b $J -c $J -o /dev/null -X POST $B/login -d "_token=$T&email=admin@example.com&password=password"
check "dashboard"                   "$(code $B/admin)" 200
check "posts list"                  "$(code $B/admin/posts)" 200
check "new post form"               "$(code $B/admin/posts/create)" 200
check "categories (admin only)"     "$(code $B/admin/categories)" 200
check "tags (admin only)"           "$(code $B/admin/tags)" 200
check "comment moderation"          "$(code $B/admin/comments)" 200
body $B/admin | grep -q 'Recent activity' && ok "activity feed (polymorphic)" || bad "activity feed" "missing"

section "8. Admin creates and publishes a post"
T=$(tok $B/admin/posts/create)
TITLE="E2E Verification Article $$"
curl -s -b $J -c $J -o /dev/null -X POST $B/admin/posts \
  -d "_token=$T" --data-urlencode "title=$TITLE" \
  --data-urlencode "body=This article was created by the end to end verification script and is long enough to pass validation." \
  -d "status=published"
NEW=$(docker compose exec -T app php artisan tinker --execute="echo App\Models\Post::where('title','like','E2E Verification%')->first()?->slug;" 2>/dev/null | tr -d '\r\n')
[ -n "$NEW" ] && ok "post created (slug: $NEW)" || bad "post created" "not found"
check "new post is public"          "$(code $B/posts/$NEW)" 200
body $B/posts/$NEW | grep -q "E2E Verification" && ok "new post renders its title" || bad "title renders" "missing"
body $B/api/posts | grep -q "E2E Verification" && ok "new post appears in the API" || bad "API" "missing"

section "9. Guest posts a comment, admin moderates it"
rm -f $J
CP=$(body $B/posts/$NEW)
CT=$(echo "$CP" | grep -oP 'name="_token"\s+value="\K[^"]+' | head -1)
STARTED=$(echo "$CP" | grep -oP 'name="started_at" value="\K[^"]+' | head -1)

# First prove the timing trap fires: posting instantly must be refused.
BEFORE_FAST=$(docker compose exec -T app php artisan tinker --execute='echo App\Models\Comment::count();' 2>/dev/null | tr -d '\r\n')
curl -s -b $J -c $J -o /dev/null -X POST $B/posts/$NEW/comments \
  -d "_token=$CT" --data-urlencode "started_at=$STARTED" -d "website=" \
  --data-urlencode "author_name=Too Fast" --data-urlencode "author_email=fast@example.com" \
  --data-urlencode "body=Submitted instantly, the way a script would."
AFTER_FAST=$(docker compose exec -T app php artisan tinker --execute='echo App\Models\Comment::count();' 2>/dev/null | tr -d '\r\n')
check "instant submission rejected" "$AFTER_FAST" "$BEFORE_FAST"

# Now behave like a person: read the form for a few seconds, then submit.
sleep 5
rm -f $J
CP=$(body $B/posts/$NEW)
CT=$(echo "$CP" | grep -oP 'name="_token"\s+value="\K[^"]+' | head -1)
STARTED=$(echo "$CP" | grep -oP 'name="started_at" value="\K[^"]+' | head -1)
sleep 5
curl -s -b $J -c $J -o /dev/null -X POST $B/posts/$NEW/comments \
  -d "_token=$CT" --data-urlencode "started_at=$STARTED" -d "website=" \
  --data-urlencode "author_name=E2E Reviewer" --data-urlencode "author_email=e2e@example.com" \
  --data-urlencode "body=A comment left by the end to end verification script."
PEND=$(docker compose exec -T app php artisan tinker --execute="echo App\Models\Comment::where('author_name','E2E Reviewer')->first()?->status->value;" 2>/dev/null | tr -d '\r\n')
check "comment saved as pending"    "$PEND" "pending"
body $B/posts/$NEW | grep -q 'E2E Reviewer' && bad "pending comment hidden" "visible" || ok "pending comment is hidden from the page"

CID=$(docker compose exec -T app php artisan tinker --execute="echo App\Models\Comment::where('author_name','E2E Reviewer')->first()?->id;" 2>/dev/null | tr -d '\r\n')
rm -f $J; T=$(tok $B/login)
curl -s -b $J -c $J -o /dev/null -X POST $B/login -d "_token=$T&email=admin@example.com&password=password"
AT=$(tok $B/admin/comments)
curl -s -b $J -c $J -o /dev/null -X POST $B/admin/comments/$CID/approve -d "_token=$AT&_method=PATCH"
APPR=$(docker compose exec -T app php artisan tinker --execute="echo App\Models\Comment::find($CID)?->status->value;" 2>/dev/null | tr -d '\r\n')
check "comment approved by admin"   "$APPR" "approved"
curl -s $B/posts/$NEW | grep -q 'E2E Reviewer' && ok "approved comment now visible publicly" || bad "approved comment visible" "missing"

section "10. Honeypot and rate limiting"
rm -f $J
CP=$(body $B/posts/$NEW); CT=$(echo "$CP" | grep -oP 'name="_token"\s+value="\K[^"]+' | head -1)
STARTED=$(echo "$CP" | grep -oP 'name="started_at" value="\K[^"]+' | head -1)
BEFORE=$(docker compose exec -T app php artisan tinker --execute='echo App\Models\Comment::count();' 2>/dev/null | tr -d '\r\n')
curl -s -b $J -c $J -o /dev/null -X POST $B/posts/$NEW/comments \
  -d "_token=$CT" --data-urlencode "started_at=$STARTED" -d "website=http://spam.example" \
  --data-urlencode "author_name=Spam Bot" --data-urlencode "author_email=bot@example.com" \
  --data-urlencode "body=Buy cheap things at my website right now."
AFTER=$(docker compose exec -T app php artisan tinker --execute='echo App\Models\Comment::count();' 2>/dev/null | tr -d '\r\n')
check "honeypot blocked the bot"    "$AFTER" "$BEFORE"

section "11. Author cannot touch another author's work"
rm -f $J; T=$(tok $B/login)
curl -s -b $J -c $J -o /dev/null -X POST $B/login -d "_token=$T&email=author@example.com&password=password"
check "author reaches the dashboard"  "$(code $B/admin)" 200
check "author reaches their posts"    "$(code $B/admin/posts)" 200
check "author BLOCKED from categories" "$(code $B/admin/categories)" 403
check "author BLOCKED from tags"       "$(code $B/admin/tags)" 403
ADMINPOST=$(docker compose exec -T app php artisan tinker --execute="echo App\Models\Post::whereHas('user', fn(\$q)=>\$q->where('email','admin@example.com'))->first()?->slug;" 2>/dev/null | tr -d '\r\n')
check "author BLOCKED editing admin's post" "$(code $B/admin/posts/$ADMINPOST/edit)" 403

section "12. Results"
printf "\n  passed: \033[32m%s\033[0m   failed: \033[31m%s\033[0m\n\n" "$PASS" "$FAIL"
rm -f $J
[ "$FAIL" -eq 0 ]
