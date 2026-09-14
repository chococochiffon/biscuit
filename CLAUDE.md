# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository layout

This repo is the top-level project container for a Laravel application. The Laravel app itself lives entirely under `app/` (i.e. `app/app`, `app/routes`, `app/database`, etc. — not to be confused with Laravel's own `app/` directory inside it). `docker/` and `docker-compose.yml` at the repo root define the local dev environment. Run all `composer`/`artisan`/`npm` commands from inside `app/`.

The project was reset to a stock Laravel 13 skeleton (see commit history) — most domain models/factories from an earlier iteration were deleted, so currently only the default `User` model/migration exist. Expect to (re)build domain models, migrations, controllers, and routes from scratch.

## Environment

Local dev runs via Docker Compose (from the repo root):

```
docker compose up -d
```

This starts:
- `app` — PHP-FPM 8.5 container (built from `docker/php/Dockerfile`), mounts `./app` to `/var/www/app`, and runs `composer install` on entrypoint.
- `nginx` — serves `app/public` on port 80, proxies `.php` requests to `app:9000`.
- `db` — MySQL 9 on port 3306 (root password `password`, database `database`).
- `phpmyadmin` — on port 8081.

Default local `.env` uses SQLite (`DB_CONNECTION=sqlite`), not the Dockerized MySQL — switch `DB_*` env vars to point at the `db` service if you need MySQL locally.

Initial setup (from `app/`):
```
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate
```

`composer run setup` bundles most of this (install, `.env` copy, key generation, migrate, npm install/build) in one step.

## Common commands

All run from `app/`:

- `php artisan dev` (or `composer dev`) — runs the app, queue listener, and Vite dev server concurrently.
- `php artisan test` (or `composer test`) — run the full test suite (clears config first). Filter to one test: `php artisan test --filter=testName` or target a file: `php artisan test tests/Feature/ExampleTest.php`.
- `vendor/bin/pint` — format PHP code (Laravel Pint).
- `php artisan migrate` / `php artisan migrate:refresh --seed` — run/reset migrations.
- `npm run dev` / `npm run build` — Vite dev server / production asset build.

Scaffolding conventions used in this project (from `README.md`):
- API controllers: `php artisan make:controller API/XxxController --resource`
- Web controllers: `php artisan make:controller XxxController --resource`
- Model + migration + factory + seeder together: `php artisan make:model Xxx -mfs`
- Standalone migration on an existing table: `php artisan make:migration create_xxx_table --table=xxx`

## Architecture notes

- Standard Laravel 13 structure with the streamlined `bootstrap/app.php` bootstrapping style (middleware/exception/routing config centralized there rather than in an `Http/Kernel.php`).
- Routing: `routes/web.php` for web routes, `routes/console.php` for Artisan commands. No `routes/api.php` yet — add one and register it in `bootstrap/app.php`'s `withRouting()` if/when an API is introduced (the README's `API/` controller convention implies API routes are planned).
- `bootstrap/app.php` already renders JSON error responses for `api/*` routes or JSON-expecting requests via `shouldRenderJsonWhen`.
- Autoloading: `App\` → `app/app/` (mind the doubled `app`), `Database\Factories\` → `app/database/factories/`, `Database\Seeders\` → `app/database/seeders/` (see `app/composer.json`).
- Default DB driver for both dev (`.env`) and testing (`phpunit.xml`) is SQLite; tests use an in-memory SQLite DB, array cache/session/mail, and sync queue.
- `app/` has its own Laravel Boost-managed AI guidance (`app/CLAUDE.md`, `app/AGENTS.md`, `app/.claude/skills/`) covering PHP/Laravel/Pint/PHPUnit conventions and exposing a Boost MCP server (`app/.mcp.json`) with tools like `database-schema` and `search-docs`. That guidance loads automatically when working inside `app/` — this file intentionally doesn't duplicate it and instead covers repo-level layout and the Docker environment.
