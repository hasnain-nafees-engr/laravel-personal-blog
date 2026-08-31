# Hasnain's Blog

A personal blog built with Laravel 13 — a public site plus an admin panel — written as a
reference implementation of idiomatic Laravel: thin controllers, Form Requests, policies,
events, queued jobs, Eloquent relationships and a real Docker setup.

| | |
|---|---|
| Framework | Laravel 13.29 (PHP 8.4) |
| Database | PostgreSQL 16 |
| Front end | Blade + Tailwind CSS 4 + Alpine.js, Livewire 3 for live search |
| Build | Vite 8 |
| Tests | Pest 4 — 193 tests, 87% coverage |
| Static analysis | Larastan (PHPStan) level 5 |
| Formatting | Laravel Pint (PSR-12) |

---

## What it does

**Public site** — paginated article index, single article by slug, category and tag
archives, live search with debounce, threaded comments with moderation, reading time,
view counter, related posts, `sitemap.xml`, RSS feed, dark/light mode.

**Admin panel** — dashboard with counts and an activity feed, full CRUD for posts,
categories and tags, comment moderation queue, cover-image upload with background
resizing, draft preview, scheduled publishing.

---

## Prerequisites

You only need **Docker**. PHP, Composer, Node and PostgreSQL all run inside containers.

| Tool | Minimum | Check |
|---|---|---|
| Docker Engine | 24+ | `docker --version` |
| Docker Compose | v2+ | `docker compose version` |
| make | any | `make --version` |

<details>
<summary>Optional: host tooling for IDE support and faster commands</summary>

Handy but not required — the app never depends on it.

```bash
# PHP 8.4 (Ubuntu 24.04 ships 8.3, so use the ondrej archive)
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl \
                    php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd php8.4-pcov

# Composer
cd /tmp && php -r "copy('https://getcomposer.org/installer','composer-setup.php');" \
  && php composer-setup.php && sudo mv composer.phar /usr/local/bin/composer
```
</details>

---

## Quick start

```bash
git clone <repository-url> laravel-project
cd laravel-project
cp .env.example .env
make up
```

That is the whole setup. `make up` installs dependencies, builds the images, starts
PostgreSQL, waits for it, runs the migrations and starts the queue worker and scheduler.

Then seed the demo content:

```bash
make fresh          # drops, re-migrates and seeds the demo blog
```

That gives you **12 published articles** with real cover photos, a draft, a post
scheduled for next week, and 31 comments including threaded replies and two waiting
in the moderation queue. The articles are genuine technical posts — several of them
document problems that came up while building this project — because lorem ipsum
would exercise none of the prose styles, reading-time estimates or Markdown rendering.

| What | Where |
|---|---|
| Blog | <http://localhost:8000> |
| Admin panel | <http://localhost:8000/admin> |
| Mailpit (catches all dev email) | <http://localhost:8025> |
| Vite dev server | <http://localhost:5173> |

**Demo logins** (created by the seeder):

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@example.com` | `password` |
| Author | `author@example.com` | `password` |

---

## The two database modes

Switch between them by editing `.env` only — no command-line flags, no file juggling.
Compose reads `COMPOSE_PROFILES` straight out of `.env`.

### Mode A — containerized PostgreSQL (default)

```dotenv
DB_HOST=postgres
COMPOSE_PROFILES=db
```

Then `make down && make up`.

Nothing to install, nothing to configure. The database lives in a Docker volume, so
`make fresh` can reset it without touching anything else on your machine.

### Mode B — your host's PostgreSQL

```dotenv
DB_HOST=host.docker.internal
COMPOSE_PROFILES=
```

Host PostgreSQL listens on `127.0.0.1` only, so containers cannot reach it until you
allow it. Run this once:

```bash
sudo ./scripts/setup-host-postgres.sh
```

It sets `listen_addresses` to include the Docker gateway, appends a `pg_hba.conf` rule
for `172.16.0.0/12`, restarts PostgreSQL, and creates the `blog` and `blog_test`
databases owned by the `.env` user. Then `make down && make up`.

Verify from inside the container:

```bash
docker compose exec app php artisan db:show
```

### Which should I use?

| | Container (A) | Host (B) |
|---|---|---|
| Setup | none | one sudo script |
| Reproducible on a new machine | yes | needs the script |
| Data survives `docker volume prune` | no | yes |
| Uses your existing psql / GUI tools | needs a published port | works as usual |
| Recommended for | everyday work, CI, demos | reusing a database you already run |

---

## Commands

Run `make help` to see them with descriptions.

| Command | What it does |
|---|---|
| `make up` | Build and start everything (installs deps if `vendor/` is missing) |
| `make down` | Stop the stack, keep the data |
| `make ps` | Service status and health |
| `make logs` | Tail every service's logs |
| `make shell` | Shell inside the app container |
| `make migrate` | Run pending migrations |
| `make fresh` | Drop everything, re-migrate, seed demo data |
| `make seed` | Seed demo data |
| `make test` | Run the Pest suite |
| `make test-coverage` | Run tests and print a coverage report (fails under 70%) |
| `make lint` | Pint style check + Larastan level 5 |
| `make fix` | Auto-fix code style |
| `make artisan cmd="route:list"` | Any Artisan command |
| `make composer cmd="require x/y"` | Any Composer command |
| `make npm cmd="install x"` | Any npm command |
| `make queue-restart` | Reload queue workers after changing job code |
| `make db-shell` | psql into the database |
| `make prod-up` | Start the production-shaped stack (no dev overrides) |

### Running the tests

```bash
make test              # 193 tests
make test-coverage     # with the coverage report
./scripts/verify-e2e.sh    # 58 checks against the running stack, over real HTTP
```

The Pest suite runs in-process with the queue, mail and filesystem faked.
`scripts/verify-e2e.sh` drives the whole stack the way a browser does — through
nginx into php-fpm, with the real queue worker and scheduler running. Both
matter: a queued job that is *dispatched* correctly can still be *broken*, and
only the second kind of test notices.

Tests run against a **real PostgreSQL** database called `blog_test`, not SQLite,
because the app uses PostgreSQL-specific SQL (`ILIKE`). Use `make test` rather than
calling `php artisan test` directly — it passes the environment variables the suite
needs, and `tests/TestCase.php` refuses to run against any database whose name does not
end in `_test`, so a mistake cannot wipe your development data.

---

## How it is put together

```
app/
├── Console/Commands/      posts:publish-scheduled
├── Contracts/             MarkdownRenderer interface
├── Enums/                 PostStatus, CommentStatus, UserRole
├── Events/                PostPublished, CommentSubmitted
├── Http/
│   ├── Controllers/       public + Admin/ + Api/
│   ├── Middleware/        EnsureUserIsAdmin (custom), SecurityHeaders
│   ├── Requests/          Form Requests - all validation lives here
│   └── Resources/         PostResource (JSON API shaping)
├── Jobs/                  OptimizeCoverImage (queued)
├── Listeners/             LogPostPublication, SendCommentNotification
├── Livewire/              PostSearch
├── Mail/                  NewCommentNotification (Markdown mailable)
├── Models/                Post, Category, Tag, Comment, User, ActivityLog
├── Observers/             PostObserver (slugs, cache invalidation)
├── Policies/              Post, Comment, Category, Tag
├── Providers/             AppServiceProvider, BlogServiceProvider (custom)
├── Rules/                 Honeypot
├── Services/              PostService, BlogQueries, CommonMarkRenderer
├── Support/               ReadingTime, CacheKeys
└── View/Components/       AppLayout, AdminLayout, SeoMeta
```

Deeper explanations live in [`docs/`](docs/):

| Document | What is in it |
|---|---|
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Request lifecycle, folder-by-folder tour, where each concept lives |
| [ERD.md](docs/ERD.md) | Schema diagram and the reasoning behind every relationship |
| [DECISIONS.md](docs/DECISIONS.md) | Every significant choice, the alternatives, the trade-off |
| [INTERVIEW.md](docs/INTERVIEW.md) | 50 likely review questions with answers and file references |
| [GLOSSARY.md](docs/GLOSSARY.md) | PHP and Laravel terms explained plainly |

---

## Troubleshooting

**1. `make up` fails with "port is already allocated"**

Something else on your machine uses port 8000 (or 5173 / 8025). Change it in `.env`:

```dotenv
APP_PORT=8001
```

Then `make down && make up`. Check what holds a port with `ss -ltnp | grep 8000`.

**2. "SQLSTATE[08006] could not connect to server" / the app container restarts**

The app cannot reach the database.

- In **container mode**, check `COMPOSE_PROFILES=db` is set in `.env` — without it the
  `postgres` service is never started. Confirm with `make ps`.
- In **host mode**, run `sudo ./scripts/setup-host-postgres.sh`, and check
  `DB_HOST=host.docker.internal`.

`docker compose logs app` shows the entrypoint's connection attempts.

**3. "Vite manifest not found" on every page**

The frontend has not been built. In development the `vite` container serves assets, so
check it is healthy:

```bash
make ps
docker compose logs vite
```

If `npm ci` failed there, `package.json` and `package-lock.json` are out of sync — run
`make npm cmd="install"` to regenerate the lock file. For a production-style run,
`make prod-up` builds the assets into the image instead.

**4. Tests fail with "Refusing to run tests against the [blog] database"**

You ran `php artisan test` directly instead of `make test`. Inside Docker, Compose
injects `.env` as real environment variables, and Laravel reads those before
`phpunit.xml`, so the suite would otherwise point at your development database. Use
`make test`.

**5. Permission denied writing to `storage/`**

The container user's ID does not match yours. Check `id -u` and set it in `.env`:

```dotenv
WWWUSER=1000
WWWGROUP=1000
```

Then rebuild: `make down && make build && make up`.

**6. `php artisan` changes have no effect**

Cached config or routes. Run `make artisan cmd="optimize:clear"`. The entrypoint does
this automatically in development, but a `php artisan optimize` run by hand persists.

**7. A job keeps failing with an error you already fixed**

Queue workers are long-running processes — they load job classes into memory once and
keep them, so editing the file changes nothing for a worker that is already running.

```bash
make queue-restart
```

The worker finishes its current job, exits, and the container restarts it with the new
code. This is a mandatory step after any real deployment too.

---

## Next steps (deployment)

Deployment is out of scope for this project, but the pieces are in place:

- `make prod-up` runs the production-shaped stack: no bind mounts, no Xdebug, assets
  compiled into the image, opcache tuned with `validate_timestamps=0`, and the
  entrypoint running `php artisan optimize` on boot.
- For a real deployment you would push `laravel-blog/app` and `laravel-blog/nginx` to a
  registry, supply configuration through the platform's secret store rather than an
  `.env` file, point `DB_HOST` at a managed PostgreSQL instance, run the queue worker
  and scheduler as separate services, and put TLS in front of nginx (at which point the
  `SecurityHeaders` middleware starts sending HSTS automatically).
- Set `APP_ENV=production` and `APP_DEBUG=false`. The custom error pages in
  `resources/views/errors/` then show instead of stack traces.
