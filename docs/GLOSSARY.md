# Glossary

PHP and Laravel terms that trip up newcomers, explained in plain words, with the place
in this project where each one shows up.

---

### Composer

PHP's package manager — the same job npm does for JavaScript. It reads `composer.json`,
downloads packages into `vendor/`, and writes an autoloader so PHP can find their classes.

Unlike npm there is no build step: Composer only manages server-side PHP code.

### `composer.json` vs `composer.lock`

`composer.json` is the wish list: version *ranges* you accept (`"laravel/framework": "^13.17"`).
`composer.lock` is the receipt: the *exact* versions that were resolved (`13.29.0`).

Both are committed. `composer install` obeys the lock file, so every machine gets identical
code; `composer update` re-resolves and rewrites the lock. That is why teammates run
*install*, not *update*.

### Autoloading / PSR-4

You never write `require` for your own classes in Laravel. PSR-4 is the convention that maps
a namespace to a folder — `App\Models\Post` lives at `app/Models/Post.php` — and Composer
generates a loader that finds a class the first time it is used.

The mapping is declared in `composer.json` under `autoload.psr-4`.

### `vendor/`

Where Composer puts downloaded packages, including Laravel itself. It is **gitignored**
because `composer.lock` can rebuild it exactly. Committing it would bloat the repository
and invite hand-edited packages that nobody could reproduce.

### Artisan

Laravel's command-line tool: `php artisan migrate`, `php artisan test`,
`php artisan make:model`. Run `php artisan list` to see everything available.

You can add your own — see [`PublishScheduledPosts`](../app/Console/Commands/PublishScheduledPosts.php).

### Service container

Laravel's object factory. Ask for a class in a constructor and the container builds it for
you, including anything *it* needs:

```php
public function __construct(private readonly PostService $posts) {}
```

That is **dependency injection**: the class states what it needs instead of calling `new`.
The payoff is testability — a test can hand it a different implementation.

### Binding

Teaching the container how to build something. In
[`BlogServiceProvider`](../app/Providers/BlogServiceProvider.php):

```php
$this->app->singleton(MarkdownRenderer::class, CommonMarkRenderer::class);
```

Now anything that asks for the *interface* receives that implementation, and swapping it is
a one-line change.

### Service provider

A class with two methods that runs during boot. `register()` may only add bindings —
nothing else has booted yet. `boot()` runs after every provider has registered, so it is the
only safe place to use facades or touch the database.

### Facade

A short static-looking way to reach a container service: `Cache::get()`, `Route::get()`,
`Storage::disk()`. `Cache` is not a class with static methods — it is a proxy that resolves
the real cache service and forwards the call. This is why facades are still mockable in tests.

### Middleware

Code that wraps a request, like layers of an onion. It can act on the way in (reject an
unauthenticated visitor) and on the way out (add a header to the response).

Example: [`EnsureUserIsAdmin`](../app/Http/Middleware/EnsureUserIsAdmin.php).

### Route model binding

When a route parameter is type-hinted as a model, Laravel fetches it for you:

```php
Route::get('/posts/{post}', [PostController::class, 'show']);
public function show(Post $post) { ... }   // already a Post, or a 404
```

This project binds by `slug` instead of `id` — see `Post::getRouteKeyName()`.

### Migration

A PHP file describing a database change (`up()` to apply, `down()` to undo). Migrations are
committed, so the schema has a history and every environment can be rebuilt with
`php artisan migrate`. Never edit a migration that has already run somewhere — add a new one.

### Seeder

Code that inserts data — demo content, or reference data a fresh install needs.
`php artisan db:seed`, or `make fresh` to rebuild and seed in one step.

### Factory

A recipe for a fake model, used by tests and seeders:

```php
Post::factory()->count(10)->published()->create();
```

`published()` is a **state**. Factories beat hardcoded fixtures because each test creates
exactly the data it needs and nothing more.

### Eloquent

Laravel's ORM (object-relational mapper). A `Post` class corresponds to the `posts` table;
`$post->title` is a column; `$post->comments` is a relationship.

### N+1 problem

Fetching a list (1 query), then reading a relation on each row (N more queries):

```php
foreach (Post::all() as $post) { echo $post->user->name; }   // 1 + N queries
```

**Eager loading** fixes it — `Post::with('user')->get()` uses two queries regardless of the
number of posts. `Model::preventLazyLoading()` turns the mistake into an exception during
development. See [`tests/Feature/EagerLoadingTest.php`](../tests/Feature/EagerLoadingTest.php).

### Query scope

A named, reusable piece of a query defined on the model:

```php
public function scopePublished(Builder $query): void { ... }
Post::published()->get();
```

Writing the rule once means no controller can implement half of it.

### Accessor / mutator

Computed attributes. An accessor derives a value on read (`$post->reading_time`); a mutator
transforms a value on write. Defined with the `Attribute` class in Laravel 9+.

### Cast

Automatic type conversion between the database and PHP: a `status` string column becomes a
`PostStatus` enum, `published_at` becomes a `Carbon` date object.

### Soft delete

"Deleting" by setting a `deleted_at` timestamp instead of removing the row. Soft-deleted
records disappear from queries automatically but can be restored. Only `posts` use it here.

### Policy vs Gate

Both answer "is this allowed?". A **policy** is about a model
(`PostPolicy::update($user, $post)`); a **gate** is a standalone check
(`Gate::allows('access-admin')`). Policies are found by convention: `Post` → `PostPolicy`.

### Form Request

A class holding the validation rules and authorization for one write operation. Type-hint it
in a controller and Laravel validates *before* the method runs — a failed check never
reaches your code. See [`StorePostRequest`](../app/Http/Requests/StorePostRequest.php).

### Blade

Laravel's template engine. `{{ $title }}` prints **escaped** output — HTML in the value is
shown as text, which stops cross-site scripting. `{!! $html !!}` prints raw HTML and is only
safe when the value is provably sanitised (in this project, exactly one place).

### Blade component vs `@include`

A **component** (`<x-post-card :post="$post" />`) takes declared props and is reusable — its
own little contract. An **`@include`** pastes a partial that inherits the surrounding scope,
useful for splitting one long template. Both appear in `posts/show.blade.php`, with comments
explaining the choice.

### CSRF

Cross-Site Request Forgery: another site tricking your browser into submitting a form to
this one, using your logged-in session. Laravel gives every session a token, `@csrf` puts it
in the form, and the middleware rejects any POST without it (HTTP 419). In Laravel 13 that
middleware is called `PreventRequestForgery` and also checks the `Sec-Fetch-Site` header.

### Queue, job, worker

A **job** is a unit of work to run later (`OptimizeCoverImage`). Dispatching it writes a row
to the `jobs` table and returns immediately. A **worker** — the `queue` container running
`php artisan queue:work` — picks jobs up and runs them.

This is why an editor's save is instant even though the image still needs resizing.

### Event and listener

An **event** announces that something happened (`PostPublished`); **listeners** decide what
that means. The publishing code does not need to know that caches must be cleared and an
audit line written — it just announces, and listeners react.

### Mailable

A class describing an email: subject (`envelope()`), body (`content()`), attachments.
A *Markdown* mailable writes the body in Markdown and renders it through Laravel's
responsive email components.

### Cache invalidation

Removing a cached value when the underlying data changes. The hard part is remembering
every place that must be cleared, which is why every key in this project is named in
[`CacheKeys`](../app/Support/CacheKeys.php) rather than typed as a string in two files.

### Config vs env

`.env` holds machine-specific values and is never committed. `config/*.php` files read those
values with `env()` and are what the application actually uses.

**`env()` must never be called outside `config/`.** In production `php artisan config:cache`
compiles the config into one file and the `.env` is no longer read at all — so an `env()`
call in a controller would suddenly return `null`. Use `config('blog.per_page')` instead.

### Pest vs PHPUnit

Pest is a friendlier syntax on top of PHPUnit — the same runner, the same assertions.
`test('it works', function () { ... })` compiles into a PHPUnit test method, so every Laravel
testing helper works unchanged. See the note at the top of
[`tests/Pest.php`](../tests/Pest.php).

### PHPStan / Larastan

Static analysis: reading code to find bugs *without running it* — a call to a method that
does not exist, a `null` where an object is required. **Larastan** teaches PHPStan about
Laravel's magic (Eloquent builders, facades, relations). This project runs at level 5 of 10.

### Pint

Laravel's code formatter. `vendor/bin/pint` rewrites code to a consistent style (PSR-12);
`--test` checks without changing anything, which is what CI runs.
