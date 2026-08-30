# Interview preparation

50 questions a reviewer is likely to ask about this project, with answers written the way
you would actually say them out loud — short, direct, no recital.

Each answer names the file that backs it up, so you can open the code while you talk.
The last section is the honest one: questions where the right move is to admit the limit.

---

## Laravel fundamentals

**1. Walk me through what happens when someone visits `/posts/my-article`.**

nginx gets the request. It sees there is no file at that path, so it hands it to php-fpm,
which runs `public/index.php`. That builds the application from `bootstrap/app.php` — service
providers register, then boot. The request passes through the middleware stack, the router
matches `/posts/{post}`, and because the parameter is type-hinted as `Post`, Laravel looks up
the slug and gives me the model — or 404s before my code runs. Then my controller runs,
fetches what the view needs, and returns a rendered Blade template. The response travels back
out through the same middleware.

*The diagram is in `docs/ARCHITECTURE.md`.*

**2. What is a service provider, and why are there two methods?**

It is where you wire things up at boot. `register()` is only for bindings — nothing else has
booted yet, so touching the database or a facade there would break. `boot()` runs after every
provider has registered, so that is where the real setup goes.

In `app/Providers/BlogServiceProvider.php` you can see it: `register()` binds the
`MarkdownRenderer` interface, and `boot()` registers the observer, enables lazy-loading
protection and adds a Blade directive.

**3. Why does `MarkdownRenderer` exist as an interface?**

So nothing depends on *how* Markdown is rendered. The container binds the interface to
`CommonMarkRenderer`, and every call site just asks for the interface. If I wanted to add
caching, or switch parsers, I change one line in the provider and nothing else moves.

I did not do that everywhere though — `ReadingTime` is a plain static helper, because it has
no dependencies and no plausible second implementation. An interface there would be ceremony.

**4. What is the service container actually doing?**

It builds objects for me. When `Admin\PostController` type-hints `PostService` in its
constructor, I never write `new PostService` — Laravel sees the type hint, builds it, and
passes it in. That is dependency injection, and the reason it matters is testing: the class
says what it needs instead of hard-coding where it comes from.

**5. Why is `env()` only allowed inside `config/`?**

Because in production `php artisan config:cache` compiles all the config into one file, and
after that Laravel never reads `.env` again. An `env()` call in a controller would return
`null` the moment you deploy — and it would work perfectly on your laptop, which is the worst
kind of bug.

So everything goes through `config/blog.php`, and code reads `config('blog.per_page')`.
Larastan enforces this too: Larastan's `noEnvCallsOutsideOfConfig` rule is on.

**6. Explain a facade. Is `Cache::get()` really a static method?**

No. `Cache` is a proxy class. The static call is intercepted, the container resolves the real
cache service, and the call is forwarded to it. That is why you can still fake it in tests
with `Cache::shouldReceive()` — a genuine static method could not be swapped like that.

**7. Middleware or policy — how do you choose?**

They answer different questions. Middleware asks "does this person belong in this *area*?"
and runs before the controller. A policy asks "may this person touch *this record*?".

I use both, and I got it wrong first. Originally I put my `admin` middleware on the whole
`/admin` group, which locked authors out of their own drafts even though `PostPolicy` allowed
them. Now `/admin` requires only `auth`, policies decide per record, and the `admin`
middleware guards just categories and tags — which really are site-wide, area-level things.

*`routes/web.php`, `app/Http/Middleware/EnsureUserIsAdmin.php`.*

**8. Show me your custom middleware.**

`EnsureUserIsAdmin`, aliased as `admin` in `bootstrap/app.php`. It delegates to a Gate rather
than checking the role itself, so the rule lives in exactly one place — a Blade view asking
`@can('access-admin')` gets the same answer.

**9. What does `bootstrap/app.php` do in Laravel 11+?**

It replaced the old `Kernel` classes. Routing, middleware and exception handling are all
configured there now with a fluent API. Mine registers the `SecurityHeaders` middleware
globally, aliases `admin`, and sets trusted proxies.

**10. Why did you configure trusted proxies?**

Because nginx sits in front of php-fpm. Without it, every request looks like it came from the
nginx container's IP — so per-IP rate limiting would treat the entire internet as one visitor,
and `$request->ip()` would be useless.

---

## Routing and controllers

**11. What is a resource controller?**

A controller using Laravel's seven conventional method names — `index`, `create`, `store`,
`show`, `edit`, `update`, `destroy`. Because the names are conventional,
`Route::resource('posts', PostController::class)` generates all seven routes with the right
verbs and names. `Admin\PostController` is one; I exclude `show`, since the admin list links
straight to edit and the public page is where you read a post.

**12. Why route model binding by slug instead of id?**

URLs should be readable and stable: `/posts/route-model-binding-explained`, not `/posts/17`.
I set it once in `Post::getRouteKeyName()` rather than writing `{post:slug}` on every route,
so it is impossible to forget on a new route.

**13. Binding gives you the model — so how do you stop people reading drafts?**

Binding only answers "does this slug exist". The editorial rule is separate, and it is in the
controller: `abort_unless($post->isPublished(), 404)`. A draft returns 404, not 403 —
a 403 would confirm the article exists, which leaks information.

The API does the same thing, and there are tests for drafts, scheduled posts and
soft-deleted posts all returning 404.

**14. Why are your controllers so short?**

Because everything else has a better home. Validation is in Form Requests, authorization in
policies, query rules in model scopes, and multi-step writes in services. What is left is
"fetch this, hand it to that", which is all a controller should be.

`Admin\PostController::store()` is three lines: call the service, redirect with a message.

**15. When is a service worth creating?**

When one conceptual action is several steps that must succeed together. Creating a post
saves the record, syncs tags, stores an upload and dispatches a job — inside a transaction.
That does not belong in a controller, and I would have to duplicate it for the API.

Simple CRUD like categories has no service — the controller calls `Category::create()`
directly, because inventing a `CategoryService` for one line would be architecture theatre.

**16. Why do some of your controllers have no method name?**

They are invokable — a single `__invoke` method. `HomeController` does one thing, so
`Route::get('/', HomeController::class)` is clearer than inventing an `index` name.

---

## Eloquent and the database

**17. Which relationships did you use?**

- `belongsTo` / `hasMany` — a post belongs to an author, an author has many posts.
- `belongsToMany` — posts and tags, through the `post_tag` pivot.
- `hasManyThrough` — `User::commentsOnPosts()`, every comment on any of my posts. There is no
  `user_id` on comments, so the link runs through posts.
- Polymorphic `morphTo` / `morphMany` — `activity_logs` records actions against either a post
  or a comment from one table.

**18. Why is the polymorphic table not just two nullable columns?**

Because then every new loggable type means another column and another migration. `morphs()`
stores the model class and its id, so logging a new type costs nothing. The dashboard loads
them with `with('subject')` and Laravel resolves each row to the right model.

**19. Explain the N+1 problem and show me where it bit you.**

You fetch a list in one query, then read a relation on each row — one extra query per row.
Invisible with three test rows, fatal with five hundred.

It bit me for real. `BlogQueries::relatedPosts()` eager-loaded `category` but the post card
also renders the author's name. Because `Model::preventLazyLoading()` is on outside
production, the page threw an exception immediately instead of quietly doing 30 extra
queries. I added `user` to the `with()` and it was fixed in a minute.

There is a before/after test in `tests/Feature/EagerLoadingTest.php`: the lazy version takes
more than 10 queries for 10 posts, the eager version takes exactly 3 — and two more tests
assert that the query count does not grow when you add rows.

**20. `Model::preventLazyLoading()` — why not in production too?**

Because in production I would rather serve a slow page than a 500. In development and CI it
throws, so the mistake never reaches production in the first place. It is
`preventLazyLoading(! $this->app->isProduction())` in `BlogServiceProvider`.

**21. Why put the "published" rule in a scope?**

Because it is two conditions — status is published *and* `published_at` is in the past — and
if any page implements only half of it, a scheduled article leaks early. `Post::published()`
is written once and used everywhere, including the API.

It also matches the composite index on `(status, published_at)`.

**22. Which indexes did you add, and why those?**

`slug` unique on posts, categories and tags, because that is the route lookup.
`(status, published_at)` composite on posts, because every public page filters on exactly
that pair. `(post_id, status)` on comments for "approved comments of this post". And every
foreign key, because joins and constraint checks use them.

**23. What happens when a category is deleted?**

The posts survive and become uncategorised — the foreign key is `nullOnDelete`. Deleting a
tag only removes pivot rows. Deleting a post cascades to its comments. Deleting an author is
blocked entirely by `restrictOnDelete`, because losing someone's articles by accident is
unacceptable.

Those choices are in the migrations, and there are tests for each.

**24. Soft deletes — where and why?**

Only on posts. Trashing and restoring an article is a real editorial workflow. A soft delete
just sets `deleted_at`, so nothing cascades — which is exactly what you want, because
restoring a post brings its whole comment thread back.

Categories, tags and comments delete for real; their foreign keys define the blast radius.

**25. Why enums instead of string columns?**

Because `PostStatus::from('wizard')` throws, while `$post->status = 'wizzard'` would silently
store nonsense. The column is a string in PostgreSQL and cast to a PHP enum on the model, so
I get type safety in code without the pain of altering a native database enum later.

The enums also carry their own `label()` and `badgeClasses()`, so the view does not need a
match statement.

**26. How does the slug get generated, and why in an observer?**

`PostObserver::creating()` generates it from the title if none was given, with a `-2`, `-3`
suffix if it collides. It is in an observer rather than the controller so the rule holds no
matter who saves the post — a seeder, a command, tinker.

On update it only regenerates if the slug was cleared, because silently changing a slug
would break every existing link to the article.

**27. How do you count views without a race condition?**

`$post->increment('view_count')`, which issues `SET view_count = view_count + 1` in the
database. Reading into PHP and writing back would lose updates when two people load the page
at once. I also set `timestamps = false` around it, so a view does not look like an edit,
and the controller only counts once per session.

**28. What is an accessor, and which do you have?**

A computed attribute. `$post->reading_time` estimates minutes from the word count,
`$post->body_html` renders the Markdown, and `$post->summary` returns the excerpt or falls
back to the opening of the body. Both of the expensive ones use `shouldCache()`, so they are
computed once per request.

---

## Security

**29. Why is `{!! $post->body_html !!}` not a cross-site scripting hole?**

Because of what produces that string. `CommonMarkRenderer` parses the Markdown with
`html_input: 'strip'` and `allow_unsafe_links: false`, so any `<script>`, `onerror=` or
`javascript:` URL is removed before the value exists. It is the only unescaped output in the
whole application, and there are four unit tests in `tests/Unit/MarkdownRendererTest.php`
that would fail the moment that stopped being true.

Everywhere else uses `{{ }}`, which escapes — including comment author names, which are
attacker-controlled and have their own test.

**30. How does CSRF protection work here?**

Laravel puts a token in the session, `@csrf` writes it into every form as a hidden field, and
the middleware rejects any POST without a match — a 419. In Laravel 13 the middleware is
called `PreventRequestForgery` and additionally checks the `Sec-Fetch-Site` header, so it
verifies the request's origin as well as the token.

**31. How do you stop comment spam?**

Three layers, none of which annoy a real person. A honeypot field hidden with CSS that must
arrive empty; an encrypted timestamp proving the form was on screen for at least a few
seconds; and a rate limiter of three comments per minute per IP. On top of that, every
comment is created as `pending` — nothing a stranger writes appears until a human approves it.

The timestamp is encrypted specifically so a bot cannot just back-date it. All of it is
tested, including the tampered-timestamp case.

**32. What is mass assignment and how are you protected?**

If you pass a whole request array into `Model::create()`, a user could add `role=admin` to
the form data and escalate themselves. `#[Fillable([...])]` on the model whitelists which
attributes may be filled — and `role` is deliberately not on the User list.

That attribute is Laravel 13's form of the classic `protected $fillable`.

**33. How are uploads validated?**

`mimes:jpg,jpeg,png,webp` checks the actual file content rather than the extension, plus a
size cap and a minimum dimension check. The file is stored under a name Laravel generates,
never the user's — an uploaded `../../.env` has no chance against a random hash. nginx also
refuses to execute anything but the front controller.

There is a test that uploads a PHP file renamed to `.jpg` and asserts it is rejected.

**34. What security headers do you send?**

`X-Content-Type-Options: nosniff` so a browser cannot decide an image is really JavaScript;
`X-Frame-Options: DENY` against clickjacking the admin panel; a `Referrer-Policy`; and a
`Permissions-Policy` denying camera, microphone and geolocation, which we never use. HSTS is
sent only over real HTTPS — sending it on `http://localhost` would pin a developer's browser
to https for the whole domain.

*`app/Http/Middleware/SecurityHeaders.php`.*

**35. Is login rate-limited?**

Yes, by Breeze — five attempts per minute keyed on email plus IP, in
`app/Http/Requests/Auth/LoginRequest.php`. I did not rewrite it; I checked it was there.
I added my own limiters for comments and the API.

**36. How do you know no secrets are committed?**

`.env` is in `.gitignore` and `.dockerignore`, so it is neither committed nor baked into an
image. Only `.env.example` is committed, with placeholder values. Configuration reaches
containers at runtime through `env_file`.

---

## Docker

**37. Why not Laravel Sail?**

Sail is a good development sandbox, but it is *only* that — one big container, code bind
mounted, no production story. I wanted a slim production image and a real service topology,
so I wrote it myself: a multi-stage build where Composer and npm run in their own stages and
the runtime image gets only the compiled result and the extensions Laravel actually needs.

The other reason is honest: I can explain every line of mine.

**38. Walk me through the Dockerfile.**

Six stages. `base` is php-fpm on Alpine with just the extensions we need — `pdo_pgsql`,
`mbstring`, `bcmath`, `gd`, `zip`, `intl`, `opcache`, `pcntl`. `vendor` runs
`composer install --no-dev`. `assets` runs `npm ci && npm run build` in a Node image.
`prod` copies the code plus those two results, runs as a non-root user, and tunes opcache
with `validate_timestamps=0`. `nginx` carries the built `public/` directory. `dev` adds
Xdebug and Composer for local work.

Copying the Composer manifests before the rest of the code means the slow install layer stays
cached until dependencies actually change.

**39. Why is `opcache.validate_timestamps` different in dev and prod?**

In production it is `0` — PHP never stats files after the first compile, which is fastest and
safe because a container's code never changes; a deploy replaces the whole image. In
development that would mean edits are ignored, so it is `1` with `revalidate_freq=0`.

**40. How do both database modes work with one compose file?**

Compose reads `COMPOSE_PROFILES` from `.env`. `COMPOSE_PROFILES=db` starts the bundled
`postgres` service; leaving it empty starts nothing extra and the app talks to the host's
PostgreSQL through `host.docker.internal`, which works on Linux thanks to
`extra_hosts: host-gateway`.

Because it is read from `.env`, switching is genuinely one file edit — no CLI flags.

**41. Why doesn't `app` use `depends_on: postgres`?**

Because in host mode that service does not exist, so the compose file would be invalid for
half the project's supported configurations. Instead the entrypoint waits for whichever
database `.env` points at, using a small PDO retry loop. That works identically in both
modes and doubles as the wait a real deployment needs anyway.

**42. Three containers share one image — how do they not all run migrations?**

`CONTAINER_ROLE` tells the entrypoint what it is. Only `app` migrates. `queue` and
`scheduler` wait until migrations are finished before starting their loop.

The ordering is enforced by health rather than by sleeping: `app` only reports healthy after
its entrypoint finishes, and the other services declare
`depends_on: app: condition: service_healthy`.

**43. What do the healthchecks actually check?**

Not just "is the process alive". php-fpm is asked through its own `/ping` endpoint using
`cgi-fcgi`. nginx fetches Laravel's `/up` route, which proves the whole chain — nginx to
php-fpm to a booted application. PostgreSQL uses `pg_isready`, Redis uses `redis-cli ping`,
and the workers check their process is still running.

---

## Testing and tooling

**44. How does Pest relate to PHPUnit?**

Pest *is* PHPUnit underneath. Each `test('...', function () {})` closure compiles into a
method on a PHPUnit test case, `beforeEach()` becomes `setUp()`, and `expect($x)->toBe(1)`
becomes `assertSame()`. So every Laravel helper works unchanged and `php artisan test` runs
both styles. I use it purely because the tests read better.

*The mapping is written out at the top of `tests/Pest.php`.*

**45. Why do your tests run on PostgreSQL instead of SQLite in memory?**

Because the app uses PostgreSQL-specific SQL. Search uses `ILIKE`, the timestamps are
`timestamptz`, and one query had to be rewritten because PostgreSQL — unlike MySQL — will
not accept a select alias in `HAVING`. A SQLite suite would have gone green while production
broke, which is worse than having no test. It costs about two seconds a run.

**46. What are the most valuable tests in the suite?**

The negative ones. A guest cannot delete a post, an author cannot delete or edit someone
else's, an author cannot reach the category admin, a draft is 404 for everyone including the
API, and a pending comment does not appear on the page. Those are the assertions that would
catch a real security regression — the happy paths mostly just prove the wiring.

**47. Something the tests caught that surprised you?**

That `phpunit.xml` was being ignored inside Docker. Compose injects `.env` through
`env_file`, so `APP_ENV` and `DB_DATABASE` are real process environment variables, and
Laravel reads `$_SERVER` before PHPUnit's `$_ENV` — even with `force="true"`. The suite was
running as `APP_ENV=local` against my *development* database, and `RefreshDatabase` wiped my
seeded posts.

`make test` now passes those variables explicitly, and `tests/TestCase.php` refuses to run
against any database whose name does not end in `_test`. That guard is the real fix — the
next person cannot make the same mistake.

**48. What does Larastan give you that tests do not?**

It reads the code without running it, so it finds paths a test never exercises. At level 5 it
caught that `User` did not implement `MustVerifyEmail` even though Breeze's routes fire the
`Verified` event that requires it — shipped scaffolding that could never have worked.

Getting it clean also meant annotating every model with `@property` docblocks, since Eloquent
resolves columns through `__get` at runtime. With `checkModelProperties` on, a typo like
`$post->titel` is now a failed analysis instead of a silent null.

**49. What does CI run, and why the Docker job?**

Four jobs: Pint for style, Larastan for static analysis, the test suite against a PostgreSQL
16 service container with a 70% coverage floor, and a build of the production image.

That last one matters because the other three run on the GitHub runner's own PHP — they would
never notice a broken Dockerfile. The image is what actually ships, so CI proves it still
builds.

**50. Your coverage is 85%. What is not covered, and does it matter?**

The uncovered parts are mostly the image-resizing job's interaction with GD, a few policy
branches, and error paths in the upload service. I would rather have honest gaps there than
write tests that assert a mock was called.

What I made sure *is* covered: every authorization rule, every validation rule, the XSS
protection, and the cache invalidation. Those are the places where a silent regression would
actually hurt.

---

## Questions I might struggle with

Being straight about a limit reads far better than bluffing. These are prepared, honest
answers — each one admits the gap and then shows the thinking.

**"How would this behave under real load?"**

I have not load tested it, so I would not want to quote numbers. What I can say is where I
would look first: the homepage and sidebar queries are cached, the public queries are covered
by the `(status, published_at)` index, and there is a test asserting the query count does not
grow with the number of rows. The obvious next steps would be to move cache and sessions to
Redis — the profile is already in the compose file — and to measure before changing anything
else.

**"Why not use Redis for cache and queues from the start?"**

For a personal blog the database driver is genuinely enough, and it means one less service to
run and explain. Redis is one line in `.env` away — `COMPOSE_PROFILES=db,cache` — so it is a
switch rather than a rewrite. The one thing I would flag is that the database cache driver
has no tag support, which is why my invalidation forgets named keys rather than flushing a
tag.

**"Is a honeypot enough against a determined spammer?"**

No. It stops naive bots, and combined with rate limiting and mandatory moderation it means
nothing bad reaches the site. But a targeted script that renders the page and waits would get
through the honeypot. If spam became a real problem I would add a proper challenge or content
filtering — I did not build for a threat this site does not have yet.

**"Why no Content-Security-Policy header?"**

I looked at it and decided against shipping one I could not verify. Livewire and Alpine both
use inline handlers, so a strict policy needs per-request nonces threaded through every
script tag, and a half-configured CSP that everyone works around with `unsafe-inline` gives
false confidence. The other headers are in place. It is the first thing I would add with time
to test it properly.

**"What would you do differently if you started again?"**

Two things. I would decide the authorization model on paper first — I initially put the admin
middleware on the whole admin area and had to unpick it once I realised it contradicted my
own policies. And I would set up the test database plumbing before writing code, because I
lost my seeded development data to a test run that was silently pointed at the wrong database.

**"Is there anything in here you are not happy with?"**

The admin post form is one long Blade partial and it is getting hard to read — I would split
it into components next. And `BlogQueries` mixes caching with querying; if it grew much more
I would separate the cache layer from the queries it wraps.
