# Architecture & Tooling Decisions

Every significant choice in this project, the alternatives considered, and the trade-off.
Newest entries at the bottom. Each entry answers: what did we choose, what else could we
have chosen, and why this one.

---

## D-001: Composer, and local vs. global Laravel installation

**Context.** Before installing anything we had to decide *how* Laravel gets into this repo.

**What Composer is.** Composer is PHP's dependency manager — the same job npm does for
JavaScript. You declare what your project needs in `composer.json`; Composer downloads
those packages into `vendor/` and generates an autoloader so PHP can find every class
(npm's closest equivalents: `package.json` and `node_modules/`). One practical
difference: npm runs a build toolchain for the browser, while Composer only manages
server-side PHP code — there is no "composer build".

**Global installer vs. project-local install.**
- `composer global require laravel/installer` gives you a `laravel new` command on your
  machine. It is only a convenience for *creating* projects; it installs nothing into them.
- `composer create-project laravel/laravel .` (what we used) downloads the Laravel
  application skeleton directly into the repo, with no machine-global state at all.

**Why Laravel itself is always per-project.** The framework (`laravel/framework`) is a
dependency in `vendor/`, never a system-wide install. Two projects on one machine can run
different Laravel versions; a `git clone` + `composer install` reproduces the exact same
framework anywhere. That is why we chose the project-local approach: the repo is
self-contained and reproducible on any machine.

**`composer.json` vs. `composer.lock`.** `composer.json` states version *ranges* we accept
(e.g. `laravel/framework: ^13.17`). `composer.lock` records the *exact* versions that were
actually resolved (e.g. `13.29.0`), so every machine and CI run installs identical code.
Both are committed. `vendor/` is **gitignored** because it is fully derivable from the lock
file — committing it would bloat the repo and invite hand-edited (irreproducible) packages.

---

## D-002: Laravel 13 instead of the requested "12.x line"

**Choice.** Laravel 13 (framework 13.29, skeleton 13.10.1).

**Alternatives.** Laravel 12, as literally written in the project brief.

**Trade-off.** The brief said "latest stable Laravel (12.x line)" — one phrase that now
names two different things: Laravel 13 has been the latest stable since 17 Mar 2026, and
Laravel 12 left its bug-fix window on 13 Aug 2026 (security fixes only until Feb 2027).
Starting a brand-new project on a version that no longer receives bug fixes is hard to
defend in review. Laravel 13 was confirmed with the project owner. Cost: we must respect
a few Laravel-13 behaviors (renamed `PreventRequestForgery` CSRF middleware, hardened
`cache.serializable_classes`, JSON session serialization) — each is documented where it
affects code.

---

## D-003: PHP 8.4

**Choice.** PHP 8.4 everywhere: the Docker runtime image and the host CLI.

**Alternatives.** PHP 8.3 (Ubuntu 24.04's default apt package), PHP 8.5 (newest).

**Trade-off.** Laravel 13 requires PHP ≥ 8.3 and supports up to 8.5. PHP 8.3 entered
security-only support in Jan 2026; PHP 8.4 receives active bug fixes until Dec 2026
(security until Dec 2028). PHP 8.5 is newest but our test tooling target (Pest 4) and the
widest ecosystem compatibility sit most comfortably on 8.4. On Ubuntu the host install
uses the community-standard `ppa:ondrej/php` archive — the extra apt source is the only
cost.

---

## D-004: Scaffolding through Docker with a pinned PHP platform

**Choice.** The skeleton was created with the official `composer:2` image
(`composer create-project laravel/laravel`), and `composer.json` sets
`config.platform.php = 8.4.0` before the first `composer install`.

**Alternatives.** (a) Install PHP on the host first and scaffold natively; (b) scaffold
with the `composer:2` image and no platform pin.

**Trade-off.** PHP and Composer were not yet present on the host, and waiting on a host
install would have blocked the project for no benefit — Docker gives the same result
reproducibly. The pin matters because the `composer:2` image currently bundles PHP 8.5.9:
without `platform.php`, Composer would resolve `composer.lock` as if the app runs on
PHP 8.5 and could select package versions our PHP 8.4 runtime refuses. The pin makes
dependency resolution behave exactly like the production runtime, no matter which PHP
executes Composer. `// why:` this is also the textbook fix whenever dev machines and
servers run different PHP versions.

---

## D-005: Hand-written Docker setup instead of Laravel Sail

**Choice.** Our own multi-stage `Dockerfile` + `docker-compose.yml` (+ dev override).

**Alternatives.** Laravel Sail, Laravel's official Docker option.

**Trade-off.** Sail is excellent for a quick dev sandbox, but it is *only* a dev
environment: one fat container with everything, code bind-mounted, no production
story. Writing our own gives us (1) a slim prod image built in stages (composer
stage, npm stage, runtime with only the needed extensions), (2) a real service
topology — php-fpm behind nginx, a separate queue worker and scheduler, health-
checked and started in dependency order, and (3) something worth defending in a
review: we can explain every line. Cost: more files to maintain than `sail up`.

## D-006: Two database modes; container mode is the out-of-the-box default

**Choice.** `.env` alone switches between host PostgreSQL
(`DB_HOST=host.docker.internal`, `COMPOSE_PROFILES=` empty) and a containerized
`postgres:16-alpine` (`DB_HOST=postgres`, `COMPOSE_PROFILES=db`). The committed
`.env.example` defaults to **container** mode.

**Alternatives.** Host mode as default (as the project brief phrased it).

**Trade-off.** The brief also demands `git clone → cp .env.example .env → make up`
with **no manual steps** — but host mode inherently needs a one-time, sudo-level
change to `postgresql.conf`/`pg_hba.conf` (host Postgres listens on 127.0.0.1
only, so containers cannot reach it). Container mode is the only default that
satisfies the reproducibility requirement; host mode is one script away
(`sudo ./scripts/setup-host-postgres.sh`) and fully documented. `// why:` Compose
reads `COMPOSE_PROFILES` from `.env`, which is what makes the switch env-only.

## D-007: `app` waits for the database in its entrypoint, not via `depends_on`

**Choice.** The entrypoint polls the DB with a small PDO loop, then migrates;
`depends_on: condition: service_healthy` is used where it is always valid
(nginx/queue/scheduler → app).

**Alternatives.** `depends_on: postgres` with a health condition on `app`.

**Trade-off.** In host-DB mode there is no `postgres` service to depend on —
a `depends_on` would break that mode. The entrypoint loop works identically in
both modes and doubles as the "wait for DB before migrating" step the deploy
needs anyway. Bonus: because `app`'s healthcheck only passes after migrations
finished, `depends_on: app healthy` gives queue and scheduler a
"schema is ready" guarantee for free.

## D-008: nginx gets its own image built from the same Dockerfile

**Choice.** A `nginx` build target (`nginx:1.30-alpine`) that copies the built
`public/` directory out of the prod stage; uploads are shared through a named
`storage` volume mounted into both containers.

**Alternatives.** (a) Bind-mount the code into nginx (dev-only trick, useless
for prod images); (b) route *everything* through php-fpm (slow static files).

**Trade-off.** nginx must be able to serve `public/` (static assets) and pass
PHP requests to php-fpm — but it lives in another container with its own
filesystem. Copying `public/` at build time keeps images immutable; the storage
volume covers runtime-generated files (uploads). One subtlety documented in the
Dockerfile: the `public/storage` symlink is baked into the nginx image because
`artisan storage:link` runs in the app container only.

## D-009: Tailwind CSS 4, not the version Breeze installs

**Choice.** Tailwind 4 with `@tailwindcss/vite`, configured in
`resources/css/app.css` via `@theme` and `@plugin`.

**Alternatives.** Keep Tailwind 3, which `breeze:install` writes (it adds
`tailwind.config.js`, `postcss.config.js` and `autoprefixer`).

**Trade-off.** Breeze 2.4's Blade stack still ships Tailwind 3 stubs, but the
Laravel 13 skeleton ships Tailwind 4. Running Breeze downgraded the project.
We restored v4 and deleted the v3 config files: the design tokens live in CSS
next to the styles that use them, there is no separate JS config to keep in
sync, and PostCSS plus autoprefixer are no longer needed at all. Cost: Breeze's
generated auth views were written against v3 utility names, so a few needed
adjusting.

## D-010: Livewire 3, and only in two places

**Choice.** `livewire/livewire ^3.8` — used for the live search box, and nowhere else.

**Alternatives.** Livewire 4 (released); Livewire everywhere; no Livewire at all.

**Trade-off.** The brief locks Livewire 3, and 3.x fully supports Laravel 13.
More interesting is the *scope*: search results should appear as you type, and
doing that with full page reloads is absurd — that earns a Livewire component.
Everything else is a plain Blade form with a normal POST, which works without
JavaScript, is trivially testable and has no hydration cost. "Use the heavy
tool only where it pays" is a better answer in review than "we used it because
we installed it".

One integration detail: **Livewire 3 bundles Alpine.js and starts it itself**,
so Breeze's `resources/js/app.js` stub — which imports and starts Alpine — was
removed. Leaving both in place double-initialises Alpine and breaks every
`x-data` component.

## D-011: Breeze, even though it is no longer Laravel's default starter kit

**Choice.** `laravel/breeze` Blade stack (`--dark --pest`).

**Alternatives.** The current official starter kits (Fortify + Livewire 4 +
Flux UI, or Inertia + React/Vue/Svelte); hand-rolled authentication.

**Trade-off.** Breeze's README now says it targets "Laravel 11.x and prior",
and the modern kits use Fortify. But Breeze publishes plain, readable
controllers into `app/Http/Controllers/Auth/` — you can read exactly how login
works — whereas Fortify hides that behind a package. For a project whose
purpose is to *demonstrate* Laravel, visible code beats convenient code. The
brief also locks Breeze. It installs and runs correctly on Laravel 13.

## D-012: Pest 4 rather than Pest 5

**Choice.** `pestphp/pest ^4` with `pestphp/pest-plugin-laravel ^4`.

**Alternatives.** Pest 5 (newest, requires PHP 8.4 and PHPUnit 13).

**Trade-off.** Pest 5 exists, but `pest-plugin-laravel` — the package that
provides `$this->get()`, `actingAs()` and the rest inside Pest — publishes its
Laravel 13 support on the 4.x line. Choosing the newest number would have meant
betting on plugin compatibility for no gain. Pest 4 runs on PHPUnit 12.5, which
is what the Laravel 13 skeleton already depends on.

## D-013: Tests run on PostgreSQL, not SQLite in memory

**Choice.** `phpunit.xml` points at a real `blog_test` PostgreSQL database.

**Alternatives.** The Laravel default, `DB_CONNECTION=sqlite` with
`DB_DATABASE=:memory:`, which is much faster.

**Trade-off.** The application uses PostgreSQL-specific SQL: `ILIKE` for
case-insensitive search, `timestamptz` columns, and a `whereHas` rewrite forced
by PostgreSQL's stricter `HAVING` rules. A SQLite suite would go green while
production broke — worse than having no test. The cost is roughly two seconds
per run and a database that has to exist, which
`docker/postgres/init-test-db.sh` creates automatically on first boot.

## D-014: Only arrays go into the cache, never Eloquent models

**Choice.** `BlogQueries` caches plain arrays of scalars.

**Alternatives.** Cache the models or collections directly, which is the usual
Laravel example.

**Trade-off.** Laravel 13 ships `cache.serializable_classes => false`, so every
cache store now unserializes with `allowed_classes: false`. A cached Eloquent
model comes back as `__PHP_Incomplete_Class` — **silently**, with no exception —
and only explodes later when a Blade view touches a property. Allow-listing the
classes is possible but re-opens the deserialization gadget-chain risk the
default exists to close. Caching arrays sidesteps it entirely and unserializes
faster. `tests/Feature/CachingTest.php` locks the rule in.

Invalidation is explicit: `PostObserver` and `LogPostPublication` forget every
key in `CacheKeys::postRelated()`. The `database` cache driver has no tag
support, so keys are named in one place rather than flushed by tag.

## D-015: The `admin` middleware guards taxonomy, policies guard records

**Choice.** `/admin` requires `auth` only. Categories and tags sit inside a
nested `->middleware('admin')` group. Everything else is authorized per record
by a policy.

**Alternatives.** Put `admin` on the whole `/admin` group (the first attempt).

**Trade-off.** Guarding the entire area with `admin` locked authors out of
their own drafts, which contradicts `PostPolicy` allowing an author to edit
their own work. The two mechanisms answer different questions — middleware asks
"does this person belong in this *area*?", a policy asks "may they touch *this
record*?" — and using each for its own question is what makes the rules
readable. Site-wide taxonomy is genuinely an area-level concern, so it keeps
the middleware.

## D-016: A `dashboard` route that redirects, instead of editing Breeze

**Choice.** `Route::redirect('/dashboard', '/admin')->name('dashboard')`.

**Alternatives.** Change `route('dashboard')` in the six Breeze files that use it.

**Trade-off.** Breeze redirects there after login, registration, email
verification and password confirmation. One redirect keeps the name valid
everywhere and survives a future `breeze:install`; six edits would have to be
redone every time.

## D-017: Hand-written sitemap and RSS instead of a package

**Choice.** `SitemapController` and `FeedController` rendering Blade templates.

**Alternatives.** `spatie/laravel-sitemap` and a feed package.

**Trade-off.** Each endpoint is one query and about thirty lines of XML. Adding
two dependencies — with their own upgrade cycles and configuration — to generate
that is a poor trade, and in a review it is better to be able to explain every
line than to point at a package.

## D-018: Markdown stored, HTML rendered on read

**Choice.** `posts.body` stores Markdown. `Post::bodyHtml()` renders it through
`CommonMarkRenderer` with `html_input: 'strip'` and `allow_unsafe_links: false`.

**Alternatives.** A WYSIWYG editor storing HTML; storing rendered HTML alongside
the source.

**Trade-off.** Storing HTML means trusting whatever the editor produced forever,
and a stored-XSS bug becomes permanent data. Storing Markdown keeps the source
of truth plain text and moves the security decision into one function that is
unit-tested (`tests/Unit/MarkdownRendererTest.php`). It is also what makes
`{!! $post->body_html !!}` defensible: the only unescaped output in the
application is provably sanitised at the point it is produced. The accessor uses
`shouldCache()` so a post renders its Markdown once per request.

## D-019: PHP attributes for mass assignment, with the classic form documented

**Choice.** `#[Fillable([...])]` on models, as the Laravel 13 skeleton does.

**Alternatives.** `protected $fillable = [...]`.

**Trade-off.** Identical behaviour; the attribute is what Laravel 13 generates.
Because a reviewer may only know the property form, every model carries a
comment naming it. Either way the security point is the same: a request cannot
set `role` just by adding it to the form data.

## D-020: Model property annotations for static analysis

**Choice.** Every model carries `@property` / `@property-read` docblocks.

**Alternatives.** Generate them with `barryvdh/laravel-ide-helper`; leave them out
and lower the Larastan level.

**Trade-off.** Eloquent resolves columns through `__get` at runtime, so without
annotations static analysis cannot know `$post->status` is a `PostStatus` — which
produced most of the level-5 errors. Writing them by hand keeps the model
self-documenting with no generation step in the build, and `checkModelProperties`
then turns a typo like `$post->titel` into a failed analysis rather than a silent
null in production. The cost is remembering to update them when a column changes.

## D-021: The queued job's own body needs its own test

**Choice.** `tests/Feature/Jobs/OptimizeCoverImageTest.php` constructs
`OptimizeCoverImage` and calls `handle()` against a faked disk, in addition to
the CRUD test that asserts the job is *dispatched*.

**Alternatives.** Rely on the existing `Queue::fake()` assertion in the admin
CRUD test.

**Trade-off.** `Queue::fake()` is the right tool for testing the *controller* —
it proves the job was dispatched without running it. But because it never
executes `handle()`, the job's body had no coverage at all, and it shipped
calling `Image::read()` — Intervention Image **v3**'s method name, which does
not exist in v4. Every test passed. Only an end-to-end upload through the
running stack surfaced it.

"The job was dispatched" and "the job works" are two separate claims, and each
needs its own test. Larastan had actually flagged the missing method, and it
was wrongly silenced with an `ignoreErrors` entry — so this also removed the
project's only suppression. `phpstan.neon` now carries no ignore rules, and a
comment explains why.

## D-022: Queue workers must be restarted when job code changes

**Choice.** A `make queue-restart` target, and `queue:work --max-time=3600` in
the compose command.

**Alternatives.** Assume the bind mount is enough in development.

**Trade-off.** A worker is a long-running PHP process: it loads job classes
into memory once and keeps them. Editing the file changes nothing for a worker
that is already running — the fixed `OptimizeCoverImage` above kept failing
with the *old* error until the worker was restarted, which is a genuinely
confusing five minutes if you have not met it before.

`php artisan queue:restart` signals workers to finish the current job and exit;
the container's restart policy brings them back with the new code. `--max-time`
is a belt-and-braces backstop: a worker recycles itself hourly regardless. In a
real deployment this is a mandatory post-deploy step.

## D-023: Real articles and committed cover photos, not Faker

**Choice.** `database/seeders/data/articles.php` holds twelve complete
articles in Markdown; twelve cover photos live in
`database/seeders/assets/covers/` and are committed to the repository.

**Alternatives.** (a) Faker paragraphs, as the first version of the seeder
used; (b) downloading cover images at seed time.

**Trade-off.** Lorem ipsum has no headings, code blocks, lists, links or
blockquotes, so the prose styles, the reading-time estimate and the Markdown
renderer were never honestly exercised — the seeded site looked plausible from
a distance and told a reviewer nothing. Real articles surface all of it, and
give the search, tag and category pages meaningful things to match.

Committing the photos (1.9 MB for twelve, re-encoded at quality 78) rather
than fetching them at seed time means `make fresh` works with no network
access, gives identical results on every machine and in CI, and cannot break
because a third-party image host is down or rate-limits.

The trade is repository size and the fact that content updates are now code
changes. At this scale both are clearly worth it; a site with hundreds of
seeded articles would want a different approach.

## D-024: `docker/setup-buildx-action` before the CI image build

**Choice.** The CI "Production image builds" job runs
`docker/setup-buildx-action@v3` before `docker/build-push-action@v6`.

**Alternatives.** Drop `cache-to`/`cache-from` and accept a slower, uncached
build every run.

**Trade-off.** The first real CI run on GitHub failed with `Cache export is
not supported for the docker driver`. `cache-to: type=gha` needs the
`docker-container` buildx driver; GitHub's runners default to the plain
`docker` driver, which builds images fine but refuses cache export outright.
`setup-buildx-action` swaps the driver in before the build step, at the cost
of a few extra seconds per run — worth it, since the whole point of this job
is fast, cached confirmation that the shipped Dockerfile still builds.
