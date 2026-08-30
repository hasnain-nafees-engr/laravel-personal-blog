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
