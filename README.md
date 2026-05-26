# Kanban Board — Laravel 12 SaaS

A production-grade **Trello-style Kanban board** built with **Laravel 12**, **Vue 3 + Inertia**, **Tailwind CSS**, and **Laravel Reverb** (real-time WebSockets). Multi-tenant workspaces, role-based access control, REST API, queued notifications, full test coverage, and free-tier deployment ready.

> Built as a portfolio project to demonstrate senior-level PHP/Laravel skills — clean architecture, proper DB design, authorization, testing, and DevOps.

---

## Table of Contents

1. [Features](#features)
2. [Tech Stack](#tech-stack)
3. [Architecture](#architecture)
4. [Database Schema (ERD)](#database-schema-erd)
5. [Prerequisites](#prerequisites)
6. [Local Setup (Step by Step)](#local-setup-step-by-step)
7. [Environment Variables](#environment-variables)
8. [Running the App](#running-the-app)
9. [API Endpoints](#api-endpoints)
10. [Real-time Events](#real-time-events)
11. [Testing](#testing)
12. [Common Issues & Fixes](#common-issues--fixes)
13. [Folder Structure](#folder-structure)
14. [Coding Standards](#coding-standards)
15. [Deployment (Free Tier)](#deployment-free-tier)
16. [CI / CD](#ci--cd)
17. [Roadmap](#roadmap)

---

## Features

### MVP (v1.0)
- Email/password auth with verification (Laravel Breeze)
- Workspaces — create, invite members by email, role-based (Owner / Admin / Member)
- Boards inside workspaces with custom backgrounds & visibility
- Lists with drag-and-drop reordering
- Cards with title, description (Markdown), due date, labels, assignees, cover
- Drag cards across lists with **real-time sync** to other users (Reverb WebSockets)
- Comments with `@mentions` (triggers notification)
- Checklists with progress bar
- File attachments (S3 / Cloudflare R2)
- Activity log per board (audit trail)
- Search across boards/cards
- Dark mode

### v1.1+ (stretch)
- Real-time presence (who is on this board now)
- Calendar view + Gantt view
- Stripe billing (Pro workspaces)
- Public board sharing via link
- Slack / Discord notifications
- Mobile app (Flutter / React Native consuming the REST API)

---

## Tech Stack

| Layer       | Technology |
|-------------|-----------|
| Backend     | Laravel 12, PHP 8.3 |
| Frontend    | Vue 3 + Inertia.js, Tailwind CSS, Vue Draggable Next |
| Auth        | Laravel Breeze (Inertia + Vue) + Sanctum (for SPA / mobile API) |
| Database    | MySQL 8 (or PostgreSQL 16) |
| Cache/Queue | Redis 7 |
| Real-time   | Laravel Reverb |
| Search      | Laravel Scout + Meilisearch |
| Storage     | Cloudflare R2 (S3-compatible) |
| Mail        | Resend (3000 free/mo) |
| Admin       | Filament 3 |
| Testing     | Pest v3 |
| CI/CD       | GitHub Actions |
| Container   | Docker + docker-compose |
| Deploy      | Oracle Cloud Free Tier / Render / Railway |

---

## Architecture

```
┌─────────────────────┐      ┌──────────────────┐
│   Vue 3 + Inertia   │─────▶│   Laravel 12 API │
│   (Browser SPA)     │ HTTP │  (Controllers)   │
└─────────────────────┘      └────────┬─────────┘
        ▲                             │
        │ WebSocket (Reverb)          ▼
        │                    ┌─────────────────┐
        └────────────────────│   Reverb Server │
                             └─────────────────┘
                                      │
              ┌────────────┬──────────┼──────────┬────────────┐
              ▼            ▼          ▼          ▼            ▼
         ┌─────────┐ ┌─────────┐ ┌────────┐ ┌────────┐ ┌──────────┐
         │  MySQL  │ │  Redis  │ │   R2   │ │ Resend │ │Meilisearch│
         │  (data) │ │ (cache, │ │(files) │ │ (mail) │ │ (search) │
         │         │ │ queue)  │ │        │ │        │ │          │
         └─────────┘ └─────────┘ └────────┘ └────────┘ └──────────┘
```

### Layered Architecture (inside Laravel)

```
HTTP Request
   │
   ▼
Route ──▶ Middleware (auth, throttle)
   │
   ▼
FormRequest (validation)
   │
   ▼
Controller (thin — orchestration only)
   │
   ▼
Service / Action class (business logic)
   │
   ▼
Model / Repository (data access)
   │
   ▼
Eloquent ──▶ Database
   │
   ▼
API Resource (response shaping)
   │
   ▼
Event ──▶ Broadcast (real-time) + Notification (email)
```

---

## Database Schema (ERD)

Full ERD lives in [`docs/SCHEMA.md`](docs/SCHEMA.md). Quick view:

```
users ──┬── workspace_members ──▶ workspaces ──┬── workspace_invitations
        │                                       │
        └── board_members ──▶ boards ◀──────────┘
                               │
                               ├── lists ──▶ cards ──┬── card_assignees ──▶ users
                               │                     │
                               ├── labels ◀──── card_label
                               │                     │
                               └── activities        ├── checklists ──▶ checklist_items
                                                     │
                                                     ├── comments ──▶ users
                                                     │
                                                     └── attachments ──▶ users
```

### Tables Summary

| Table | Purpose |
|-------|---------|
| `users` | Auth — Laravel default + `avatar_url`, `timezone` |
| `workspaces` | Top-level tenant. Each user can own/join many |
| `workspace_members` | Pivot — user ↔ workspace with `role` |
| `workspace_invitations` | Pending email invites, expiring tokens |
| `boards` | A Kanban board belongs to a workspace |
| `board_members` | Pivot — granular per-board access |
| `lists` | Columns inside a board (To Do, Doing, Done) |
| `cards` | The task itself |
| `card_assignees` | Many-to-many users assigned to a card |
| `labels` | Per-board colored tags |
| `card_label` | Pivot card ↔ label |
| `checklists` | Subtasks inside a card |
| `checklist_items` | Individual checklist line items |
| `comments` | Threaded card discussion |
| `attachments` | Files on a card (S3/R2) |
| `activities` | Audit log — every action on workspace/board/card |
| `notifications` | Laravel default — DB notifications |

Common columns on every table: `id` (ULID or bigint), `created_at`, `updated_at`, `deleted_at` (soft delete where applicable).

---

## Prerequisites

Make sure these are installed:

| Tool | Version | Notes |
|------|---------|-------|
| **PHP** | **8.3.x** (strict — not 8.2, not 8.5) | Required by Laravel 12 framework. Larastan 3.x + Pest 3 + Reverb all tested on 8.3 / 8.4 |
| **Composer** | 2.7+ | |
| **Node.js** | 20+ | Vite 6, Inertia, Vue 3 |
| **npm** | 10+ | |
| **MySQL** | 8.0+ | Or PostgreSQL 16. **MariaDB 10.6+ also works** but use MySQL for production parity |
| **Redis** | 7+ | **Optional on Windows** — use `file`/`database` drivers instead (see below) |
| **Git** | any modern version | |
| Docker Desktop | optional | Recommended for consistent dev environment |

### Required PHP extensions

```
bcmath  ctype  curl  dom  fileinfo  filter  gd  hash  intl  mbstring
openssl  pcre  PDO  pdo_mysql  Phar  session  sodium  tokenizer  xml  zip
```

Most WAMP / Laragon / XAMPP bundles ship these by default. Verify with `php -m`.

### Check versions
```bash
php -v          # MUST show 8.3.x
composer -V
node -v         # MUST be 20+
npm -v
mysql --version
redis-cli ping  # PONG — only required if you use Redis drivers
```

### Windows + WAMP users — read this first

WAMP 3.3 ships with **PHP up to 8.2** by default. You **must** add PHP 8.3 manually:

1. Download **`php-8.3.x-Win32-vs16-x64.zip`** (Thread Safe, x64) from <https://windows.php.net/downloads/releases/archives/> — do **NOT** download the `test-pack`, `nts`, or `debug-pack` variants.
2. Extract to `C:\wamp64\bin\php\php8.3.x\` (folder name must match the `php8.3.x` pattern so WAMP detects it).
3. Restart WAMP, then WAMP icon → **PHP → Version → 8.3.x** AND **Tools → Change PHP CLI version → 8.3.x**.
4. Update Windows `Path` env var: remove the old `php8.2.x` entry, add the new `php8.3.x` entry. Restart your shell.
5. In `C:\wamp64\bin\php\php8.3.x\php.ini`, uncomment the extensions listed above (remove leading `;`).
6. Verify: `php -v` should now print **8.3.x**.

If your machine has **no Redis** (typical on Windows), skip installing it — the `.env` instructions below switch to file/database drivers automatically.

---

## Local Setup (Step by Step)

> The scaffold ships with `app/`, `database/`, `routes/`, `docs/`, `composer.json`, `.env.example` etc. but **not** the Laravel framework files (`vendor/`, `bootstrap/`, `config/`, `public/`, `resources/`, `storage/`, `tests/`, `artisan`). Step 2 below generates them by running `composer create-project laravel/laravel ...` in a sibling temp folder and copying the framework files into our scaffold. This keeps the scaffold lean in version control.

For a more detailed walkthrough, see [`GETTING_STARTED.md`](GETTING_STARTED.md).

### 1. Clone the repo
```bash
git clone https://github.com/<your-username>/kanban.git
cd kanban
```

### 2. Generate the Laravel 12 skeleton and merge

From the **parent** folder of `kanban/`:
```bash
composer create-project laravel/laravel kanban-tmp "^12.0" --prefer-dist --no-interaction
```

Then copy framework-only files **into** `kanban/` (do NOT overwrite our `app/`, `database/`, `routes/`, `composer.json`, `.env.example`, `README.md`, `GETTING_STARTED.md`, `docker/`, `docs/`, `phpstan.neon`, `pint.json`, `docker-compose.yml`).

**PowerShell (Windows)**
```powershell
$src = "kanban-tmp"
$dst = "kanban"
foreach ($f in @("bootstrap","config","public","resources","storage","tests")) {
    Copy-Item "$src\$f" "$dst\$f" -Recurse -Force
}
foreach ($f in @("artisan","vite.config.js","package.json","phpunit.xml",".editorconfig",".gitattributes")) {
    Copy-Item "$src\$f" "$dst\$f" -Force
}
New-Item -ItemType Directory -Force -Path "$dst\app\Providers" | Out-Null
Copy-Item "$src\app\Providers\*" "$dst\app\Providers\" -Recurse -Force
# Framework migrations we DO want (cache + jobs); skip the users one (we have a richer version)
Copy-Item "$src\database\migrations\0001_01_01_000001_create_cache_table.php" "$dst\database\migrations\" -Force
Copy-Item "$src\database\migrations\0001_01_01_000002_create_jobs_table.php"  "$dst\database\migrations\" -Force
Remove-Item -Recurse -Force $src
```

**macOS / Linux**
```bash
SRC=kanban-tmp ; DST=kanban
for f in bootstrap config public resources storage tests; do
  cp -R "$SRC/$f" "$DST/"
done
for f in artisan vite.config.js package.json phpunit.xml .editorconfig .gitattributes; do
  cp "$SRC/$f" "$DST/"
done
mkdir -p "$DST/app/Providers"
cp -R "$SRC/app/Providers/." "$DST/app/Providers/"
cp "$SRC/database/migrations/0001_01_01_000001_create_cache_table.php" "$DST/database/migrations/"
cp "$SRC/database/migrations/0001_01_01_000002_create_jobs_table.php"  "$DST/database/migrations/"
rm -rf "$SRC"
```

### 3. Install PHP dependencies
```bash
cd kanban
composer install --prefer-dist --no-interaction
```

> If you cloned a fork that pinned `larastan/larastan: ^2.9` or `phpstan/phpstan: ^1.12`, composer will fail with a Laravel-12 conflict. The fix lives in `composer.json` — make sure those lines are `larastan/larastan: ^3.0` and `phpstan/phpstan: ^2.0` (the scaffold already ships with these).

### 4. Install JS dependencies
```bash
npm install
```

### 5. Create `.env` and generate APP_KEY
```bash
cp .env.example .env
php artisan key:generate
```

### 6. Configure `.env` for your local platform

#### Linux / macOS (with Redis available)
Leave the `.env` defaults — they use `redis` for cache/session/queue.

#### Windows (WAMP / Laragon, no native Redis)
Override these three lines in `.env`:
```env
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database
```

#### Database (all platforms)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kanban
DB_USERNAME=root
DB_PASSWORD=         # blank for WAMP default; set your real password elsewhere
```

### 7. Create the database

**Linux / macOS**
```bash
mysql -u root -e "CREATE DATABASE kanban CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Windows (WAMP)**
```powershell
& "C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe" -u root -h 127.0.0.1 -P 3306 `
  -e "CREATE DATABASE IF NOT EXISTS kanban CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 8. Install Sanctum (API auth) and Broadcasting (Reverb)
```bash
php artisan install:api          --no-interaction
php artisan install:broadcasting --reverb --no-interaction
```

> `install:api` **prints `ERROR  API routes file already exists`** because our scaffold ships its own `routes/api.php`. That's expected — the error is harmless, but it also means the command **skips registering the API route group** in `bootstrap/app.php`. We patch that in step 10.

### 9. Install Breeze (Vue + Inertia + SSR + dark mode)
```bash
php artisan breeze:install vue --ssr --dark --no-interaction
```

> Breeze rewrites `app/Providers/AppServiceProvider.php` and adds `Vite::prefetch(...)`. Re-add `Schema::defaultStringLength(191)` to its `boot()` (see step 11) — otherwise the cache-table migration fails on MySQL with `1071 Specified key was too long`.

### 10. Register the API route group in `bootstrap/app.php`

Open `bootstrap/app.php` and make `withRouting()` look like this:
```php
->withRouting(
    web:        __DIR__.'/../routes/web.php',
    api:        __DIR__.'/../routes/api.php',
    commands:   __DIR__.'/../routes/console.php',
    channels:   __DIR__.'/../routes/channels.php',
    apiPrefix:  'api',
    health:     '/up',
)
```

### 11. Patch `AppServiceProvider` for MySQL key-length safety

Replace `app/Providers/AppServiceProvider.php` with:
```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Schema::defaultStringLength(191);   // utf8mb4 × 191 = 764 bytes — safe under 1000-byte InnoDB index limit
        Vite::prefetch(concurrency: 3);     // added by Breeze
    }
}
```

### 12. Run migrations + seed demo data
```bash
php artisan config:clear
php artisan migrate:fresh --seed --no-interaction
```

This creates 18 tables and seeds a **demo user**:
- **Email:** `demo@kanban.test`
- **Password:** `password`
- 1 workspace (*Acme Corp*), 5 members, 1 board (*Sprint Board*), 4 lists, 12 cards, 4 labels.

### 13. Storage symlink
```bash
php artisan storage:link
```

### 14. Build / dev frontend assets
```bash
npm run build    # production build (recommended for first verify)
# OR
npm run dev      # Vite dev server with HMR (use during active frontend work)
```

### 15. Smoke test
```bash
# Smoke test home page
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8000     # expect 200

# API login → returns Sanctum token
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@kanban.test","password":"password"}'
```

Visit **<http://localhost:8000>**, click *Log in*, use `demo@kanban.test` / `password`. You should land on the Breeze dashboard.

---

## Environment Variables

Key variables in `.env`:

```env
APP_NAME=Kanban
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kanban
DB_USERNAME=root
DB_PASSWORD=

# --- Default drivers (Linux / macOS / Docker with Redis) ---
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CLIENT=phpredis

# Broadcasting (Reverb — pure-PHP, works on Windows too)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=kanban-local
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8000,127.0.0.1,127.0.0.1:8000

# Mail (use 'log' in dev so emails land in storage/logs; swap to Resend in prod)
MAIL_MAILER=log
RESEND_API_KEY=

# Storage (Cloudflare R2 — S3-compatible)
FILESYSTEM_DISK=local       # switch to 's3' / 'r2' in production
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_ENDPOINT=

# Search (Meilisearch) — enable only when SCOUT_DRIVER=meilisearch
# SCOUT_DRIVER=meilisearch
# MEILISEARCH_HOST=http://localhost:7700
# MEILISEARCH_KEY=
```

### Windows / WAMP overrides (no Redis required)

Replace the three driver lines above with:
```env
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database
```

The Laravel framework migrations included in this scaffold (`0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`) create the tables `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` — required when you use the `database` queue / `file` cache fallback. Nothing else needs to change.

---

## Running the App

Open **4 separate terminals** (or use a tool like `concurrently` / Laravel Solo):

```bash
# Terminal 1 — HTTP server (explicit host avoids Windows Firewall prompts)
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — Queue worker (required when QUEUE_CONNECTION=database)
php artisan queue:work --tries=3

# Terminal 3 — Reverb WebSocket server (listens on 0.0.0.0:8080 by default)
php artisan reverb:start

# Terminal 4 — Vite dev server with HMR (only during active frontend work)
npm run dev
```

Visit: **<http://localhost:8000>**

If you're not actively editing Vue / CSS, skip terminal 4 — Laravel will serve the pre-built bundle from `public/build/`.

> Pro tip: Use `php artisan solo` (Laravel Solo package) to run all four in one terminal — or write a small `concurrently` script in `package.json`.

---

## API Endpoints

All endpoints are prefixed with `/api/v1` and require **Sanctum bearer token** (except auth).

### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | Register new user |
| POST | `/api/v1/auth/login` | Returns Sanctum token |
| POST | `/api/v1/auth/logout` | Revoke current token |
| GET  | `/api/v1/auth/me` | Current user profile |

### Workspaces
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/api/v1/workspaces` | List my workspaces |
| POST   | `/api/v1/workspaces` | Create workspace |
| GET    | `/api/v1/workspaces/{workspace}` | Get details |
| PATCH  | `/api/v1/workspaces/{workspace}` | Update |
| DELETE | `/api/v1/workspaces/{workspace}` | Delete (owner only) |
| POST   | `/api/v1/workspaces/{workspace}/invite` | Invite a user by email |
| POST   | `/api/v1/invitations/{token}/accept` | Accept invite |

### Boards
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/api/v1/workspaces/{workspace}/boards` | List boards in workspace |
| POST   | `/api/v1/workspaces/{workspace}/boards` | Create board |
| GET    | `/api/v1/boards/{board}` | Board with lists & cards (eager loaded) |
| PATCH  | `/api/v1/boards/{board}` | Update |
| DELETE | `/api/v1/boards/{board}` | Soft delete |
| POST   | `/api/v1/boards/{board}/archive` | Archive |

### Lists
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/api/v1/boards/{board}/lists` | Create list |
| PATCH  | `/api/v1/lists/{list}` | Update (name, color) |
| PATCH  | `/api/v1/lists/{list}/move` | Reorder list (`position`) |
| DELETE | `/api/v1/lists/{list}` | Delete |

### Cards
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/api/v1/lists/{list}/cards` | Create card |
| GET    | `/api/v1/cards/{card}` | Get card details |
| PATCH  | `/api/v1/cards/{card}` | Update |
| PATCH  | `/api/v1/cards/{card}/move` | Move to another list / reorder |
| DELETE | `/api/v1/cards/{card}` | Soft delete |
| POST   | `/api/v1/cards/{card}/assignees` | Add assignee |
| POST   | `/api/v1/cards/{card}/labels` | Toggle label |
| POST   | `/api/v1/cards/{card}/comments` | Add comment |
| POST   | `/api/v1/cards/{card}/checklists` | Add checklist |
| POST   | `/api/v1/cards/{card}/attachments` | Upload file |

Full OpenAPI spec: [`docs/openapi.yaml`](docs/openapi.yaml) — import into Postman.

---

## Real-time Events

Broadcast on private channel `board.{boardId}`:

| Event | Payload | When |
|-------|---------|------|
| `CardCreated` | `{ card, list_id }` | New card added |
| `CardUpdated` | `{ card }` | Title/desc/due changed |
| `CardMoved`   | `{ card_id, from_list, to_list, position }` | Drag-drop |
| `CardDeleted` | `{ card_id }` | Card removed |
| `ListReordered` | `{ list_id, position }` | List moved |
| `CommentAdded` | `{ comment }` | New comment |
| `UserJoinedBoard` | `{ user }` | Presence channel |

Frontend listens via **Laravel Echo + Pusher protocol**:

```js
window.Echo.private(`board.${boardId}`)
  .listen('CardMoved', (e) => store.applyMove(e))
  .listen('CardCreated', (e) => store.addCard(e.card));
```

---

## Testing

```bash
# Run all tests
php artisan test

# With coverage
php artisan test --coverage --min=80

# Specific suite
php artisan test --testsuite=Feature

# Pest watch mode
./vendor/bin/pest --watch
```

Target: **80%+ coverage** on Models, Policies, Controllers.

---

## Common Issues & Fixes

Battle-tested fixes from real Day-1 setups (Windows + WAMP especially). Each row maps an exact error message you might see to the precise fix.

### A. PHP / Composer

| Symptom | Root cause | Fix |
|---|---|---|
| `composer install` fails with `laravel/framework[v12.x]` cannot coexist + `larastan/larastan ... requires illuminate/support ^9 \|\| ^10 \|\| ^11` | Larastan 2.x doesn't support Laravel 12. | In `composer.json` set `"larastan/larastan": "^3.0"` and `"phpstan/phpstan": "^2.0"`, then `composer update`. |
| `php -v` shows 8.2.x even after installing 8.3 | Windows `Path` still points to the old PHP folder. | Edit Environment Variables → remove the `php8.2.x` entry, add `php8.3.x`, restart your shell. Confirm with `where.exe php`. |
| `composer create-project` downloads `php-test-pack-*.zip` instead of PHP runtime | Downloaded the wrong archive from windows.php.net. | The runtime is `php-8.3.x-Win32-vs16-x64.zip` (~30 MB, has `php.exe` inside). The `test-pack` / `nts` / `debug-pack` / `devel-pack` variants are for PHP developers, not for running Laravel. |
| `composer install` runs but `php artisan` fails with `Class "PDO" not found` | PHP extensions not enabled in `php.ini`. | Uncomment all extensions listed under [Prerequisites › Required PHP extensions](#required-php-extensions). |

### B. Migrations / MySQL

| Symptom | Root cause | Fix |
|---|---|---|
| `SQLSTATE[42000]: 1071 Specified key was too long; max key length is 1000 bytes` on `create_cache_table` | WAMP's MySQL InnoDB has a tighter index limit than Laravel's `varchar(255) utf8mb4` default (1020 bytes > 1000). | Add `Schema::defaultStringLength(191);` at the top of `AppServiceProvider::boot()` (already shown in setup step 11). Then `php artisan config:clear && php artisan migrate:fresh`. |
| `SQLSTATE[42S01]: 1050 Table 'jobs' already exists` while running `2026_01_01_000015_create_notifications_table` | The original scaffold migration also created `jobs`, `failed_jobs`, `personal_access_tokens` — now redundant because we use Laravel's `0001_01_01_000002_create_jobs_table.php` and Sanctum's published migration. | The notifications migration in this scaffold only creates the `notifications` table. If your fork still has the duplicates, delete the extra `Schema::create('jobs'/'failed_jobs'/'personal_access_tokens')` blocks. |
| `SQLSTATE[42S22]: 1054 Unknown column 'created_at' in 'field list'` while seeding `workspace_members` | The Eloquent relationship uses `->withTimestamps()` but the migration didn't add `$table->timestamps()` to the pivot. | Add `$table->timestamps();` to `2026_01_01_000003_create_workspace_members_table.php` (already fixed in scaffold). Re-run `migrate:fresh --seed`. |
| `SQLSTATE[HY000] [2002]` / `Connection refused` to MySQL | MySQL service is not running. | WAMP icon must be **green**. If orange/red, click → MySQL → Service administration → Start. |
| `SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'` | Wrong password in `.env`. | WAMP default is **blank** — set `DB_PASSWORD=` (no value). If you set a password elsewhere, match it. |

### C. Laravel install commands

| Symptom | Root cause | Fix |
|---|---|---|
| `php artisan install:api` prints `ERROR  API routes file already exists.` | Our scaffold ships `routes/api.php`, so Laravel's installer skips creating it — **and also skips registering the `api:` route group in `bootstrap/app.php`**. | Manually add `api: __DIR__.'/../routes/api.php', apiPrefix: 'api',` inside `withRouting()` in `bootstrap/app.php` (shown in setup step 10). |
| After `breeze:install`, `Schema::defaultStringLength(191)` no longer takes effect | Breeze **overwrites** `app/Providers/AppServiceProvider.php` to add `Vite::prefetch(...)`. | Re-edit the file to include **both** `Schema::defaultStringLength(191)` and `Vite::prefetch(concurrency: 3)` (shown in setup step 11). |
| `php artisan install:broadcasting` hangs on a prompt | The Reverb flag was missing. | Pass `--reverb --no-interaction`. |
| `npm run build` after Breeze fails with `Cannot find module '@inertiajs/vue3'` | npm packages were not refreshed after Breeze added them. | `npm install` again, then `npm run build`. |

### D. Runtime / Dev server

| Symptom | Root cause | Fix |
|---|---|---|
| `419 CSRF token mismatch` when hitting an API endpoint | Treating Sanctum bearer tokens like cookie auth. | Send `Authorization: Bearer <token>`; **do not** rely on CSRF for API endpoints. |
| `403 Unauthorized` from the broadcast `board.X` channel | User is not a member of the workspace owning that board. | Confirm `Workspace::hasMember($user)` returns `true`. Re-seed if needed. |
| `Connection refused` on port `8080` when starting Reverb | Port already in use (another Reverb / Pusher emulator). | Set a different `REVERB_PORT=` in `.env` and also update `VITE_REVERB_PORT`. |
| `Vite manifest not found` on the welcome page | Frontend never built. | Run `npm run build` once (or keep `npm run dev` running). |
| `php artisan serve` reports `0.0.0.0:8000` but browser shows `ERR_CONNECTION_REFUSED` | Windows Firewall blocked the listener. | Bind explicitly: `php artisan serve --host=127.0.0.1 --port=8000`. |

### E. WAMP-specific gotchas

| Symptom | Fix |
|---|---|
| WAMP icon stays orange (services partially up) | Usually port 80 is taken by IIS / Skype / Slack helper. Right-click WAMP → *Tools → Test Port 80* and change Apache's port if needed (we don't need Apache for `php artisan serve` anyway). |
| `mysql.exe` not on `Path` | Use the full path: `C:\wamp64\bin\mysql\mysql<version>\bin\mysql.exe` or add it to `Path`. |
| Permission denied creating `storage/` or `bootstrap/cache/` files | Run your shell as Administrator once, or `icacls storage /grant "Users:(OI)(CI)F"`. |

---

## Folder Structure

```
kanban/
├── app/
│   ├── Enums/                # WorkspaceRole, BoardRole, ActivityAction
│   ├── Events/               # CardCreated, CardMoved, ...
│   ├── Http/
│   │   ├── Controllers/Api/  # API controllers (thin)
│   │   ├── Requests/         # FormRequest validation
│   │   ├── Resources/        # API JSON shaping
│   │   └── Middleware/
│   ├── Models/               # Eloquent models
│   ├── Notifications/        # Mail + DB notifications
│   ├── Policies/             # Authorization
│   └── Services/             # Business logic
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docs/
│   ├── SCHEMA.md             # Full ERD + table specs
│   ├── API.md                # Detailed API examples
│   └── openapi.yaml
├── docker/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── php.ini
├── resources/
│   ├── js/
│   │   ├── Components/
│   │   ├── Pages/
│   │   └── app.js
│   └── views/
├── routes/
│   ├── api.php
│   ├── channels.php          # Broadcasting channels
│   └── web.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── .github/workflows/ci.yml  # GitHub Actions
├── docker-compose.yml
├── composer.json
└── README.md
```

---

## Coding Standards

This project follows industry best practices.

### PHP / Laravel
- **PSR-12** code style — enforced via Laravel Pint (`./vendor/bin/pint`)
- **Strict types**: `declare(strict_types=1);` at top of every PHP file
- **Type hints everywhere** — parameters & return types
- **Final classes** by default
- **Readonly DTOs** where applicable
- **Form Requests** for ALL validation (never validate in controller)
- **API Resources** for ALL responses (never return raw models)
- **Policies** for ALL authorization (never check in controller)
- **Services / Actions** for business logic > 10 lines
- **Database transactions** for multi-step writes
- **Events + Listeners** for side-effects (emails, broadcasts)
- **No N+1** — always eager load (`with`)
- **No raw SQL** unless necessary; use query builder
- **Soft deletes** for user-facing entities

### Frontend
- Vue 3 `<script setup>` + Composition API
- TypeScript (optional but recommended)
- Tailwind for styling — no custom CSS unless necessary
- Component naming: `PascalCase.vue`
- Composables: `useXxx.ts`

### Git
- Branch naming: `feature/board-drag-drop`, `fix/card-delete-policy`
- Commits: **Conventional Commits** (`feat:`, `fix:`, `chore:`, `docs:`, `test:`)
- Every PR: tests + at least one screenshot if UI change
- No direct push to `main` — only via PR

---

## Deployment (Free Tier)

### Option A — Render.com (easiest, sleeps after 15 min)

1. Push repo to GitHub.
2. On Render: **New → Web Service** → connect repo.
3. Set build command:
   ```
   composer install --no-dev --optimize-autoloader && npm ci && npm run build && php artisan migrate --force
   ```
4. Set start command:
   ```
   php artisan serve --host=0.0.0.0 --port=$PORT
   ```
5. Add free **Neon Postgres** addon (or external Neon DB).
6. Add **Upstash Redis** free tier.
7. Paste all `.env` values in Render's Environment tab.
8. Deploy.

### Option B — Oracle Cloud Always Free (best performance)

1. Create an Oracle account → launch a free **VM.Standard.A1.Flex** (ARM, 4 vCPU, 24 GB RAM — *free forever*).
2. Open ports 80, 443, 8080 in security list.
3. SSH in and run the bootstrap script in `docker/bootstrap.sh`.
4. Clone repo, run `docker compose up -d`.
5. Point domain via Cloudflare → enable free SSL via Cloudflare Tunnel or Certbot.

### Option C — Railway ($5 free credit/mo)

1. `railway init` → `railway up`.
2. Add MySQL + Redis plugins.
3. Set env vars via `railway variables`.

> Full deployment guide: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

---

## CI / CD

`.github/workflows/ci.yml` runs on every push/PR:

1. PHP 8.3 + Composer install
2. Copy `.env.testing`
3. Run migrations on a SQLite in-memory DB
4. Run **Laravel Pint** style check
5. Run **PHPStan** level 6 static analysis
6. Run **Pest** tests with coverage
7. On push to `main`: deploy via SSH to Oracle VM (or trigger Render hook)

---

## Roadmap

- [x] Auth + Workspace + Board CRUD
- [x] List + Card drag-and-drop
- [x] Real-time sync via Reverb
- [x] Comments + Activity log
- [ ] File attachments to R2
- [ ] Stripe billing (Pro plan)
- [ ] Calendar / Gantt views
- [ ] Mobile app (Flutter)
- [ ] AI: auto-suggest labels & due dates (Gemini API)

---

## License

MIT — free to use, fork, and learn from.

## Author

**Your Name** — [LinkedIn](#) · [Portfolio](#) · [Email](mailto:you@example.com)

Built as a learning project to level up from **3.5-year PHP developer** to **Senior Software Engineer**.
