<?php

/**
 * Real article content for the demo blog.
 *
 * why a data file and not Faker: a blog full of "Lorem ipsum dolor sit amet"
 * tells a reviewer nothing about how the site reads, and lorem text has no
 * headings, code blocks or links, so the prose styles, reading-time estimate
 * and Markdown rendering never get exercised honestly.
 *
 * Each entry maps to one post. `cover` names a file in
 * database/seeders/assets/covers/ which the seeder copies onto the public
 * disk. Bodies are Markdown - the same format the editor writes in.
 */

return [

    [
        'title' => 'Why your Laravel app is slow: the N+1 problem',
        'category' => 'Laravel',
        'tags' => ['eloquent', 'performance'],
        'cover' => 'n-plus-one.jpg',
        'excerpt' => 'One query to fetch the list, then one more for every single row. It is invisible on your laptop and brutal in production. Here is how to see it, and three ways to stop it.',
        'days_ago' => 3,
        'body' => <<<'MD'
        Your index page loads in 40ms locally with twelve rows of test data. In production, with
        five hundred posts, it takes four seconds. Nothing in the code changed. What happened?

        Almost always, this.

        ## What N+1 actually means

        You run one query to fetch a list. Then, while rendering, you touch a relationship on each
        row — and every touch is another query.

        ```php
        // 1 query for the posts...
        foreach (Post::all() as $post) {
            echo $post->user->name;      // ...+1 query. Every. Single. Time.
        }
        ```

        Twelve posts on your laptop: thirteen queries, 40ms, unnoticeable. Five hundred posts in
        production: five hundred and one queries. The database is not slow — you are just asking
        it five hundred separate questions that could have been one.

        ## Fix one: eager loading

        Tell Eloquent up front which relationships you need.

        ```php
        foreach (Post::with('user')->get() as $post) {
            echo $post->user->name;      // no extra queries
        }
        ```

        Two queries now, no matter how many posts: one for the posts, one that fetches every
        author whose id appeared. Laravel matches them up in PHP. Add `withCount('comments')` and
        you get the comment totals in the same pass instead of another query per row.

        ## Fix two: make it impossible to forget

        Remembering to write `with()` is not a strategy. Turn the mistake into an exception:

        ```php
        // AppServiceProvider::boot()
        Model::preventLazyLoading(! app()->isProduction());
        ```

        Now a missing `with()` throws a `LazyLoadingViolationException` in development and CI,
        and does nothing in production — because a slow page still beats a 500.

        This is not theoretical. Building this blog, that line threw the moment I opened an
        article page. The related-posts query eager-loaded `category`, but the card component
        also renders the author's name. The guard found it in seconds; without it the page would
        have quietly done thirty extra queries forever.

        > One caveat worth knowing: Laravel only arms the guard when a query returns **more than
        > one row**. Reading a relation on a single model is one extra query, not an N+1 pattern,
        > so the framework deliberately allows it.

        ## Fix three: assert it in a test

        Query counts are a property worth locking down.

        ```php
        it('does not add queries as posts are added', function () {
            Post::factory()->count(9)->published()->create();
            $withNine = countQueries(fn () => $this->get(route('posts.index')));

            Post::factory()->count(9)->published()->create();
            $withEighteen = countQueries(fn () => $this->get(route('posts.index')));

            expect($withEighteen)->toBeLessThanOrEqual($withNine);
        });
        ```

        If someone later adds `$post->category->name` to a template without updating the
        controller, this fails in CI rather than in production.

        ## The short version

        Eager load what the view touches. Turn lazy loading into an exception outside production.
        Then write one test that says the query count must not grow with the data — because that
        is the actual property you care about.
        MD,
    ],

    [
        'title' => 'Route model binding by slug, and the trap nobody mentions',
        'category' => 'Laravel',
        'tags' => ['eloquent', 'php'],
        'cover' => 'route-binding.jpg',
        'excerpt' => 'Binding a slug to a model is two lines. Knowing what it does *not* check is what stops you leaking unpublished drafts.',
        'days_ago' => 9,
        'body' => <<<'MD'
        Route model binding is one of the first things that makes Laravel feel worth it. You type
        a model in the controller signature and it arrives, already fetched.

        ```php
        Route::get('/posts/{post}', [PostController::class, 'show']);

        public function show(Post $post)   // already a Post, or an automatic 404
        ```

        By default it looks up by primary key, so the URL is `/posts/17`. Nobody wants that.

        ## Binding by slug

        Two ways. Per route:

        ```php
        Route::get('/posts/{post:slug}', ...);
        ```

        Or once, on the model:

        ```php
        public function getRouteKeyName(): string
        {
            return 'slug';
        }
        ```

        I prefer the model. The per-route version has to be repeated on every route that touches a
        post — the web page, the preview, the API — and the day someone forgets, that one route
        silently expects an id. Putting it on the model makes it impossible to get wrong.

        Add a unique index on the column, because every request is now looking it up:

        ```php
        $table->string('slug')->unique();
        ```

        ## Here is the trap

        Binding answers exactly one question: **does a row with this slug exist?**

        It knows nothing about whether the post is published, scheduled for next week, or sitting
        in the trash. If you stop at binding, every draft you have ever written is one guessed URL
        away from being public.

        The editorial rule is a separate check:

        ```php
        public function show(Post $post): View
        {
            // A draft or scheduled post must look exactly like a URL that does not exist.
            abort_unless($post->isPublished(), 404);
            // ...
        }
        ```

        ## Why 404 and not 403

        A 403 says "this exists, you may not see it". That confirms the article is real, and for a
        scheduled post the slug alone can leak an unannounced product name.

        404 says nothing at all. Use it.

        ## Soft deletes are handled for you

        A soft-deleted post is already excluded — the global scope applies to binding too, so a
        trashed article 404s without any extra work. That is one of the quiet benefits of soft
        deletes over a `is_deleted` boolean you have to remember everywhere.

        Test all three states. They are three lines each and they are the difference between a
        blog and an accidental press release.
        MD,
    ],

    [
        'title' => 'Form Requests: where validation actually belongs',
        'category' => 'Laravel',
        'tags' => ['php', 'security'],
        'cover' => 'form-requests.jpg',
        'excerpt' => 'Inline $request->validate() is fine until the second place needs the same rules. Then it is a liability.',
        'days_ago' => 16,
        'body' => <<<'MD'
        Every Laravel tutorial starts here:

        ```php
        public function store(Request $request)
        {
            $validated = $request->validate([
                'title' => 'required|max:180',
                'body' => 'required|min:20',
            ]);
        }
        ```

        Nothing wrong with it — until the update method needs the same rules with one difference,
        and the API needs them too, and now the same list exists in three places and only two of
        them have been updated.

        ## The alternative

        ```php
        class StorePostRequest extends FormRequest
        {
            public function authorize(): bool
            {
                return $this->user()?->can('create', Post::class) ?? false;
            }

            public function rules(): array
            {
                return [
                    'title' => ['required', 'string', 'max:180'],
                    'body' => ['required', 'string', 'min:20'],
                    'status' => ['required', Rule::enum(PostStatus::class)],
                ];
            }
        }
        ```

        Type-hint it and the controller shrinks to the thing it is actually for:

        ```php
        public function store(StorePostRequest $request)
        {
            $post = $this->posts->create($request->postAttributes(), $request->user());

            return redirect()->route('admin.posts.edit', $post);
        }
        ```

        ## Four things you get

        **Authorization runs first.** `authorize()` is checked before validation, and before your
        method exists. A user who may not create posts never reaches your code at all.

        **Update inherits from store.** Almost all the rules are shared. The one that differs is
        uniqueness, which must ignore the record being edited:

        ```php
        class UpdatePostRequest extends StorePostRequest
        {
            public function rules(): array
            {
                $rules = parent::rules();
                $rules['slug'] = ['nullable', Rule::unique('posts')->ignore($this->route('post'))];

                return $rules;
            }
        }
        ```

        Forget `ignore()` and saving an unchanged post fails "slug already taken" — against itself.

        **Input can be cleaned before the rules see it.** `prepareForValidation()` runs first:

        ```php
        protected function prepareForValidation(): void
        {
            $this->merge(['slug' => str($this->input('slug'))->slug()->value()]);
        }
        ```

        **It is testable on its own.** A Form Request is a class. You do not need a controller, a
        route or a browser to prove a rule works.

        ## The rule of thumb

        If validation exists in exactly one place and always will, inline is fine. The moment a
        second caller appears — an update method, an API, an import command — move it. The cost of
        moving later is a bug in whichever copy you forgot.
        MD,
    ],

    [
        'title' => 'Policies and middleware answer different questions',
        'category' => 'Laravel',
        'tags' => ['security', 'php'],
        'cover' => 'policies.jpg',
        'excerpt' => 'I put my admin middleware on the whole admin area and locked my own authors out. The fix was understanding which question each tool answers.',
        'days_ago' => 24,
        'body' => <<<'MD'
        Laravel gives you two ways to say "no". They look interchangeable. They are not, and
        using the wrong one produces rules that contradict each other.

        ## The two questions

        **Middleware** asks: *does this person belong in this area at all?* It runs before the
        controller, knows nothing about any particular record, and is coarse by design.

        **A policy** asks: *may this person touch this record?* It receives the user and the
        model, and can compare them.

        ## How I got it wrong

        My admin panel started like this:

        ```php
        Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
            Route::resource('posts', PostController::class);
            Route::resource('categories', CategoryController::class);
        });
        ```

        Sensible-looking. It was also wrong, and my own tests told me so.

        My `PostPolicy` said an author may edit their own posts:

        ```php
        public function update(User $user, Post $post): bool
        {
            return $user->isAdmin() || $post->user_id === $user->id;
        }
        ```

        But that policy never ran. The middleware rejected every author at the door, so the
        careful per-record rule underneath was decorative. Two mechanisms, contradicting each
        other, and the blunter one won.

        ## The fix

        Ask each question where it belongs.

        ```php
        Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
            // Signed-in users may be here. What they may DO is a policy question.
            Route::resource('posts', PostController::class);

            // Taxonomy shapes the whole site - that IS an area-level concern.
            Route::middleware('admin')->group(function () {
                Route::resource('categories', CategoryController::class);
                Route::resource('tags', TagController::class);
            });
        });
        ```

        Authors reach their own posts and the policy decides what they may edit. Categories and
        tags stay admin-only, because an author has no business renaming the categories every
        other author writes under. That is genuinely a question about the *area*, so middleware
        is the right tool.

        ## Keep the rule in one place

        My middleware does not check the role itself. It asks a Gate:

        ```php
        if (! Gate::allows('access-admin')) {
            abort(403, 'This area is for administrators only.');
        }
        ```

        The Gate is defined once, so a Blade view asking `@can('access-admin')` gets exactly the
        same answer. A button that would 403 is never rendered in the first place.

        ## What convinced me

        The negative tests. Not "an admin can delete a post" — that passes almost by accident —
        but:

        - a guest cannot delete a post
        - an author cannot delete **someone else's** post
        - an author cannot reach the category admin

        Those are the assertions that would catch a real regression. Write those first.
        MD,
    ],

    [
        'title' => 'A production Dockerfile for Laravel, stage by stage',
        'category' => 'Engineering',
        'tags' => ['docker', 'devops'],
        'cover' => 'dockerfile.jpg',
        'excerpt' => 'Sail is a fine sandbox but it is not a deployment. Here is a multi-stage build that produces a 190MB runtime image, explained line by line.',
        'days_ago' => 31,
        'body' => <<<'MD'
        Laravel Sail is a good place to start and a bad place to finish. It is one large container
        with your code bind-mounted — excellent for development, and no help at all when you need
        an image to actually deploy.

        Writing your own is less work than it sounds, and you end up able to explain every line.

        ## The shape

        Four ideas do most of the work:

        1. Install PHP dependencies in their own stage.
        2. Build frontend assets in a Node stage.
        3. Copy only the *results* into a slim runtime image.
        4. Run as a non-root user.

        ```dockerfile
        FROM php:8.4-fpm-alpine AS base

        ADD --chmod=0755 \
            https://github.com/mlocati/docker-php-extension-installer/releases/download/2.11.12/install-php-extensions \
            /usr/local/bin/

        RUN install-php-extensions pdo_pgsql mbstring bcmath gd zip intl opcache pcntl
        ```

        Only what the application needs. Every extra extension is attack surface and image size.

        ## Dependencies get their own stage

        ```dockerfile
        FROM composer:2 AS vendor
        WORKDIR /app

        COPY composer.json composer.lock ./
        RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

        COPY . .
        RUN composer dump-autoload --optimize --no-dev --classmap-authoritative
        ```

        Copying the manifests **before** the rest of the code is the point. Docker caches layers,
        so the slow install step is reused until `composer.json` or the lock file actually change.
        Copy everything first and every source edit re-downloads the internet.

        ## Assets never touch the PHP image

        ```dockerfile
        FROM node:22-alpine AS assets
        COPY package.json package-lock.json ./
        RUN npm ci
        COPY vite.config.js resources public ./
        RUN npm run build
        ```

        Node exists to produce `public/build`. It has no business in the image you deploy.

        ## The runtime image

        ```dockerfile
        FROM base AS prod
        COPY --chown=app:app . .
        COPY --from=vendor --chown=app:app /app/vendor ./vendor
        COPY --from=assets --chown=app:app /app/public/build ./public/build
        USER app
        ```

        Neither Composer nor Node is present. The result is about 190MB.

        ## The opcache setting that matters

        ```ini
        opcache.enable = 1
        opcache.validate_timestamps = 0
        ```

        `validate_timestamps=0` means PHP never checks whether a file changed after compiling it
        once — the fastest possible setting, and completely safe in a container, because the code
        in a container never changes. A deploy replaces the whole image.

        Set it in development and your edits are ignored, which is a memorable afternoon. Use a
        separate ini file per target.

        ## One more thing: the UID

        ```dockerfile
        ARG WWWUSER=1000
        RUN adduser -D -u ${WWWUSER} -G app app
        ```

        In development the repo is bind-mounted. If the container user's id does not match yours,
        every file the app writes is owned by someone else and you spend an hour on
        `Permission denied`. Make it a build argument and pass `id -u`.
        MD,
    ],

    [
        'title' => 'Why I stopped testing on SQLite',
        'category' => 'Engineering',
        'tags' => ['testing', 'postgres'],
        'cover' => 'sqlite-tests.jpg',
        'excerpt' => 'An in-memory SQLite suite is fast, green, and quietly testing a different application than the one you deploy.',
        'days_ago' => 38,
        'body' => <<<'MD'
        Laravel's default test configuration is SQLite in memory. It is fast, needs no setup, and
        for a while I used it without thinking.

        Then I wrote a case-insensitive search.

        ```php
        public function scopeSearch(Builder $query, string $term): void
        {
            $query->where('title', 'ILIKE', "%{$term}%");
        }
        ```

        `ILIKE` is PostgreSQL's case-insensitive `LIKE`. SQLite has no such operator. Depending on
        how the query is built you get either an error or — worse — a query that behaves
        differently from production while your suite reports success.

        ## It is not just one operator

        Once you look, the gaps are everywhere:

        - **`ILIKE`**, as above.
        - **Strict `HAVING`.** MySQL lets you reference a `SELECT` alias in `HAVING`. PostgreSQL
          does not. I had `->having('posts_count', '>', 0)` after a `withCount()` and PostgreSQL
          rejected it outright. The fix was `whereHas()`, which emits an `EXISTS` clause and is
          correct everywhere — but I only learned that because the test ran on the real engine.
        - **Timezone-aware timestamps.** `timestamptz` is a real type in PostgreSQL and an
          approximation in SQLite.
        - **Foreign key enforcement.** SQLite does not enforce them unless you switch it on, so a
          cascade rule you rely on may never be exercised.

        ## What it costs to do properly

        About two seconds a run, and a database that has to exist.

        ```xml
        <env name="DB_CONNECTION" value="pgsql" force="true"/>
        <env name="DB_DATABASE" value="blog_test" force="true"/>
        ```

        Creating it is a one-line init script in the Postgres container, so a fresh clone still
        works with no manual steps. CI runs a `postgres:16` service container — five lines of
        YAML.

        ## The seatbelt you will want

        Pointing tests at a real database means pointing them at the *wrong* real database is now
        possible, and `RefreshDatabase` will happily wipe it. I know, because it wiped mine.

        ```php
        protected function setUp(): void
        {
            parent::setUp();

            $database = config('database.connections.'.config('database.default').'.database');

            if (! str_ends_with((string) $database, '_test')) {
                throw new RuntimeException("Refusing to run tests against [{$database}].");
            }
        }
        ```

        Six lines. Nobody can make that mistake again, including me.

        ## The principle

        A test suite is a claim that the application works. If it runs against a different
        database engine than production, it is a claim about a different application.

        Two seconds is a fair price for the claim being true.
        MD,
    ],

    [
        'title' => 'What actually happens when you dispatch a job',
        'category' => 'Laravel',
        'tags' => ['queues', 'php'],
        'cover' => 'queues.jpg',
        'excerpt' => 'Queues stop being mysterious the moment you follow one job from dispatch to execution — including the part where the worker runs your old code.',
        'days_ago' => 46,
        'body' => <<<'MD'
        Queues sound advanced. They are not. Follow one job end to end and the whole idea takes
        about five minutes.

        ## The problem

        An editor uploads a 4MB cover photo. Resizing it takes two seconds of CPU. If that happens
        during the request, the editor stares at a spinner for two seconds after clicking Save,
        for work they do not care about.

        ## The move

        Store the original, respond immediately, resize later.

        ```php
        OptimizeCoverImage::dispatch($post->id, $post->cover_image);
        ```

        That line writes a row into the `jobs` table and returns. The response is already on its
        way to the browser.

        ## What a job is

        A class with a `handle()` method and one interface:

        ```php
        #[Tries(3)]
        #[Backoff(15)]
        class OptimizeCoverImage implements ShouldQueue
        {
            use Queueable;

            public function __construct(
                public readonly int $postId,
                public readonly string $path,
            ) {}

            public function handle(): void
            {
                // ...resize...
            }
        }
        ```

        `ShouldQueue` is the whole switch. `#[Tries(3)]` retries a transient failure three times;
        `#[Backoff(15)]` waits fifteen seconds between attempts.

        ## Pass ids, not models

        The constructor takes `$postId`, not `$post`. A job is serialized to the database and may
        run minutes later, so it must cope with the world having moved on:

        ```php
        $post = Post::find($this->postId);

        if ($post === null || $post->cover_image !== $this->path) {
            return;   // deleted, or the cover was replaced. Nothing to do.
        }
        ```

        Laravel's `SerializesModels` re-fetches models for you, but being explicit makes the
        "what if it is gone" case impossible to overlook.

        ## The worker

        A separate long-running process:

        ```
        php artisan queue:work --tries=3 --backoff=5 --max-time=3600
        ```

        It polls the table, runs jobs, and marks them done. In production it is its own container
        or supervisor process.

        ## The part that will confuse you once

        A worker loads your job classes into memory **when it starts**. Editing the file changes
        nothing for a worker that is already running.

        I fixed a genuine bug in a job, redeployed, and watched it fail with the *old* error.
        Nothing was cached. The worker was simply still running the code from before.

        ```
        php artisan queue:restart
        ```

        That tells workers to finish the current job and exit; your process manager restarts them
        with the new code. It is a mandatory step in any deploy that touches job classes, and
        `--max-time=3600` is a useful backstop that recycles workers hourly regardless.

        ## And the trap underneath it

        `Queue::fake()` is the standard way to test that a job was dispatched:

        ```php
        Queue::assertPushed(OptimizeCoverImage::class);
        ```

        That is a good test of the *controller*. It is not a test of the job — a faked queue never
        calls `handle()`.

        My job shipped calling a method that does not exist in the installed version of the image
        library. Every test passed. Only a real upload through the running stack found it.

        "The job was dispatched" and "the job works" are two different claims. Write both.
        MD,
    ],

    [
        'title' => 'Your .env file lies to you inside Docker',
        'category' => 'Engineering',
        'tags' => ['docker', 'devops', 'testing'],
        'cover' => 'dotenv-docker.jpg',
        'excerpt' => 'Three separate bugs, one cause: when Compose passes .env into a container, the process environment beats the file — and beats phpunit.xml too.',
        'days_ago' => 54,
        'body' => <<<'MD'
        This one cost me an afternoon and a database, so it is worth writing down.

        ## The setup

        A normal Laravel container gets its configuration through Compose:

        ```yaml
        services:
          app:
            env_file: .env
        ```

        Every line in `.env` becomes a **real process environment variable** inside the container.
        That sounds like the same thing as Laravel reading `.env`. It is not.

        Laravel's environment reader consults `$_SERVER` **before** the `.env` file. So whatever
        Compose injected always wins.

        ## Bug one: the test suite ate my development database

        `phpunit.xml` looks authoritative:

        ```xml
        <env name="APP_ENV" value="testing" force="true"/>
        <env name="DB_DATABASE" value="blog_test" force="true"/>
        ```

        Even `force="true"` only writes `$_ENV`. It does not touch `$_SERVER`. So inside the
        container my suite ran as `APP_ENV=local` against `DB_DATABASE=blog` — the **development**
        database — and `RefreshDatabase` did exactly what it says on the tin.

        I lost thirty seeded posts and gained a lasting respect for this behaviour.

        The fix is to set them where they actually win:

        ```makefile
        test:
        	docker compose exec -e APP_ENV=testing -e DB_DATABASE=blog_test app php artisan test
        ```

        Plus a guard in `TestCase` that refuses any database whose name does not end in `_test`.
        Belt and braces, because the belt already failed once.

        ## Bug two: a fresh clone was completely broken

        Same cause, different symptom. `.env.example` ships with an empty key:

        ```dotenv
        APP_KEY=
        ```

        My container entrypoint helpfully ran `php artisan key:generate`, which wrote a real key
        into `.env`. Every page still returned 500 with `MissingAppKeyException`.

        Of course it did. php-fpm had already started with `APP_KEY=""` in its environment.
        Writing to the file changed nothing for the running process.

        Two fixes, both worth having:

        ```sh
        # in the entrypoint: export it into the shell php-fpm inherits
        php artisan key:generate --force
        APP_KEY=$(grep '^APP_KEY=' .env | cut -d= -f2-)
        export APP_KEY
        ```

        and generate the key **before** the containers start at all, in the Makefile.

        ## Bug three: Compose does not notice

        Changing the *contents* of `.env` does not necessarily make Compose recreate a container.
        After generating a key you may still be running the old environment until:

        ```
        docker compose down && docker compose up -d
        ```

        ## The rule

        Inside a container, `.env` is a **build-time input**, not a live source of truth. The
        process environment is what the application actually reads.

        Anything that must be true at runtime has to be passed as a real environment variable —
        and anything you write to `.env` while a process is running will be ignored by it.
        MD,
    ],

    [
        'title' => 'Index the query you actually run',
        'category' => 'Data',
        'tags' => ['postgres', 'performance'],
        'cover' => 'indexing.jpg',
        'excerpt' => 'Adding an index to every column is not a strategy. Look at the WHERE clause your application really sends, and index that.',
        'days_ago' => 62,
        'body' => <<<'MD'
        Indexing advice usually arrives as folklore: "index your foreign keys", "don't over-index".
        Both are true and neither tells you what to do on a Tuesday.

        Here is the version I find useful: **look at the queries your application actually sends,
        and index those.**

        ## Start from the query

        Nearly every public page on this blog runs the same filter:

        ```php
        public function scopePublished(Builder $query): void
        {
            $query->where('status', PostStatus::Published)
                  ->where('published_at', '<=', now());
        }
        ```

        Two columns, always together, on every page. So:

        ```php
        $table->index(['status', 'published_at']);
        ```

        One **composite** index, not two separate ones. PostgreSQL can walk it directly: find the
        published rows, then range-scan the dates within them.

        ## Column order is not arbitrary

        A composite index can be used for a prefix of its columns, left to right.
        `(status, published_at)` serves:

        - `WHERE status = ?`
        - `WHERE status = ? AND published_at <= ?`

        but **not** `WHERE published_at <= ?` on its own. Put the column you always filter on
        first, and the range condition last.

        ## The lookup columns

        Every article page is `/posts/{slug}`, so:

        ```php
        $table->string('slug')->unique();
        ```

        `unique()` creates an index as a side effect, and gets you correctness for free — two posts
        cannot share a slug, enforced by the database rather than by hopeful application code.

        ## Foreign keys

        Laravel's `foreignId()->constrained()` creates the constraint. On PostgreSQL it does not
        automatically index the referencing column, and you want that index: joins use it, and so
        does the check that runs when the parent row is deleted.

        ## Filtered relationships

        The comment thread on a post only shows approved comments:

        ```sql
        WHERE post_id = ? AND status = 'approved'
        ```

        So the index is `(post_id, status)`, not `post_id` alone.

        ## What over-indexing costs

        Every index is a structure the database maintains on **every** write. An index nothing
        reads is pure cost: slower inserts, slower updates, more disk, more to keep in memory.

        Before adding one, ask which query it serves. If you cannot name the query, do not add it.

        ## Check your work

        ```sql
        EXPLAIN ANALYZE
        SELECT * FROM posts
        WHERE status = 'published' AND published_at <= now()
        ORDER BY published_at DESC LIMIT 9;
        ```

        `Index Scan` means it is working. `Seq Scan` on a large table means it is not — the
        database is reading every row.

        On thirty seeded posts you will see `Seq Scan` regardless, because scanning thirty rows is
        cheaper than consulting an index. That is not a bug. It is the planner being smarter than
        your intuition, which is the whole reason to measure instead of guess.
        MD,
    ],

    [
        'title' => 'Caching is easy. Invalidation is the job.',
        'category' => 'Engineering',
        'tags' => ['performance', 'php'],
        'cover' => 'caching.jpg',
        'excerpt' => 'Cache::remember takes a minute to write. Deciding when to forget it is the part that decides whether your site shows stale content.',
        'days_ago' => 71,
        'body' => <<<'MD'
        Adding a cache is one line:

        ```php
        Cache::remember('sidebar:categories', 600, fn () => Category::withCount('posts')->get());
        ```

        Ten minutes of free speed. Also ten minutes during which a new category does not appear,
        and the author who just created it reloads the page three times wondering what broke.

        The caching is not the work. The forgetting is.

        ## Name every key in one place

        Cache bugs usually come from a key typed as a string literal in two files, where only one
        of them gets cleared.

        ```php
        final class CacheKeys
        {
            public const SIDEBAR_CATEGORIES = 'blog:sidebar-categories';
            public const DASHBOARD_COUNTS = 'blog:dashboard-counts';

            public static function postRelated(): array
            {
                return [self::SIDEBAR_CATEGORIES, self::DASHBOARD_COUNTS, /* ... */];
            }
        }
        ```

        Now forgetting a key is a compile-time mistake, not a silent one.

        ## Invalidate where the change happens

        Not in the controller — a controller is only one of the ways data changes. An observer
        catches all of them: seeders, commands, tinker, a future API.

        ```php
        class PostObserver
        {
            public function saved(Post $post): void
            {
                foreach (CacheKeys::postRelated() as $key) {
                    Cache::forget($key);
                }
            }

            public function deleted(Post $post): void { /* same */ }
            public function restored(Post $post): void { /* same */ }
        }
        ```

        ## Tags are not always available

        `Cache::tags(['posts'])->flush()` is lovely and does not exist on the `file` or `database`
        drivers. If you are using the database cache — perfectly reasonable at small scale — you
        forget keys by name. Which is another reason to have them all in one class.

        ## The Laravel 13 change that will bite you

        Laravel 13 ships with:

        ```php
        'serializable_classes' => false,
        ```

        Every cache store now unserializes with `allowed_classes: false`. Cache an Eloquent model
        and it comes back as `__PHP_Incomplete_Class` — **silently**. No exception at write time,
        none at read time. It explodes later, in a Blade view, touching a property.

        The setting exists for a good reason: it closes a deserialization gadget-chain attack if
        your `APP_KEY` ever leaks. So the answer is not to switch it off.

        The answer is to cache **arrays**:

        ```php
        Cache::remember(CacheKeys::SIDEBAR_CATEGORIES, 600, fn () => Category::query()
            ->withCount('posts')
            ->get()
            ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'count' => $c->posts_count])
            ->all());
        ```

        Sidesteps the problem entirely, and unserializes faster anyway.

        ## Test the invalidation, not the cache

        Nobody needs a test proving `Cache::remember` caches. Test the thing that actually breaks:

        ```php
        it('clears post caches when a post is saved', function () {
            Cache::put(CacheKeys::SIDEBAR_CATEGORIES, ['stale'], 600);

            Post::factory()->published()->create();

            expect(Cache::has(CacheKeys::SIDEBAR_CATEGORIES))->toBeFalse();
        });
        ```
        MD,
    ],

    [
        'title' => 'How to read a stack trace without panicking',
        'category' => 'Career',
        'tags' => ['php', 'testing'],
        'cover' => 'stack-trace.jpg',
        'excerpt' => 'Sixty lines of red text contain roughly three useful ones. Here is how to find them.',
        'days_ago' => 80,
        'body' => <<<'MD'
        A stack trace is not a punishment. It is the most direct answer you will ever get to
        "what went wrong", and most of it is noise you can learn to skip in about a week.

        ## Read three things, in this order

        **1. The message.** The first line. Read it slowly and literally.

        ```
        Call to undefined method Intervention\Image\ImageManager::read()
        ```

        That is not vague. A method named `read` does not exist on that class. Before theorising
        about anything, check whether it is a typo, a version difference, or the wrong class.

        (It was a version difference. `read()` is that library's v3 name; v4 calls it
        `decodePath()`.)

        **2. The first line that is *your* code.** Traces read newest-first and usually start deep
        inside the framework. Scroll until you see a path that is not `vendor/`:

        ```
        #0 vendor/laravel/framework/.../Facade.php:364
        #1 app/Jobs/OptimizeCoverImage.php(51)      <-- start here
        ```

        Everything above that is *how* you got there. Line 51 is *what* you did.

        **3. Whether it happened where you think.** If the trace names a file you did not expect,
        that is the finding. Chasing the wrong file is the most common way to lose an hour.

        ## Learn a few error shapes

        Most errors are one of a handful of species:

        | Message | Almost always means |
        |---|---|
        | `Call to a member function x() on null` | Something you assumed existed returned null |
        | `Undefined variable $x` (in a view) | The controller did not pass it, or a loop shadowed it |
        | `Class "X" not found` | Missing `use` statement, or a namespace typo |
        | `SQLSTATE[42703] Undefined column` | The query names a column the table does not have |
        | `419 Page Expired` | Missing `@csrf`, or the session was lost |
        | `Undefined method ::scopeX()` | A scope called on the wrong model's builder |

        Recognising the shape gets you to the cause faster than reading every line.

        ## The one that taught me most

        `Undefined variable $post` in a Blade view, on a page that obviously passed `$post`.

        It did. Then this ran further down the same template:

        ```blade
        @foreach ($related as $post)
            <x-post-card :post="$post" />
        @endforeach
        ```

        A `@foreach` variable leaks into the surrounding scope. After the loop, `$post` was the
        last *related* article, not the one being displayed. The trace pointed at the comments
        partial — a file that was completely innocent.

        The lesson is not about Blade. It is that the line a trace points to is where the problem
        **surfaced**, which is not always where it was **caused**.

        ## When you are properly stuck

        Make the failure smaller. Delete code until it stops happening, then put back the last
        thing. A failing case you can reproduce in three lines is nearly solved; the same bug
        inside a full request is a research project.

        And read the message again. It is astonishing how often it said exactly what was wrong the
        first time.
        MD,
    ],

    [
        'title' => 'What I look for in a code review',
        'category' => 'Career',
        'tags' => ['testing', 'security'],
        'cover' => 'code-review.jpg',
        'excerpt' => 'Style is a formatter\'s job. Here is what is actually worth a human\'s attention.',
        'days_ago' => 90,
        'body' => <<<'MD'
        A review that says "add a space here" and approves a missing authorization check has spent
        its attention badly. Formatting is a machine's job — Pint reformats this whole codebase in
        two seconds. Static analysis catches a whole class of type errors before anyone looks.

        So what is a human for?

        ## 1. What happens when this fails?

        The happy path is usually fine; people test the happy path. I look for the edges:

        - The API call times out. Then what?
        - The record was deleted between the page loading and the form submitting.
        - Two people click the button at the same time.

        In this codebase that last one shaped a real decision. Counting a view could have been:

        ```php
        $post->view_count = $post->view_count + 1;   // read, add, write - loses updates
        $post->save();
        ```

        It is:

        ```php
        $post->increment('view_count');   // SET view_count = view_count + 1, in the database
        ```

        Two readers at once, two counted views. The first version loses one.

        ## 2. Is the security check in the right layer?

        Not "is there a check", but "can it be bypassed by a different route in?" A rule enforced
        in a controller is absent from the API, the console command and the seeder. A rule in a
        policy, an observer or a database constraint holds everywhere.

        And I always look for the negative test. "An admin can delete a post" passes almost by
        accident. "**A guest cannot**" and "**an author cannot delete someone else's**" are the
        ones that catch a regression.

        ## 3. Will the query count grow with the data?

        Anything inside a loop that touches the database. Twelve rows of test data hides it; five
        hundred rows in production does not.

        ## 4. Does the name say what it does?

        `handle()`, `process()`, `data` — these tell a reader nothing. `publishScheduledPosts()`
        does. A good name removes the need for a comment; a comment explaining a bad name is a
        workaround.

        ## 5. Are the comments about *why*, not *what*?

        ```php
        // Increment the view count.        <-- says what the next line already says
        $post->increment('view_count');
        ```

        versus

        ```php
        // why: increment() adds in the database, so two simultaneous readers
        // cannot overwrite each other the way read-modify-write would.
        $post->increment('view_count');
        ```

        The second one survives being read in a year. Code shows what; only a comment can carry
        the reason a different, more obvious approach was rejected.

        ## 6. Is this the smallest thing that could work?

        Not the cleverest. A service class for one line of CRUD is architecture theatre. So is an
        interface with exactly one implementation and no plausible second one.

        The reverse is also true: four steps that must succeed together belong in a transaction in
        a service, not scattered through a controller.

        ## And how to say it

        Ask rather than instruct. "What happens if this is null?" invites the author to look;
        "this is broken" invites them to defend. You are frequently the one who has misread the
        code — I have been, plenty of times — and a question costs nothing when that happens.

        Say what is good, too. It is the only signal anyone gets that a thing is worth repeating.
        MD,
    ],

];
