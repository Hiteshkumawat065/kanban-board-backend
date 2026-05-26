# Kanban — Split Backend / Frontend Workspace

This repository now contains two independently-managed applications that
together form the Kanban product:

| Folder      | What it is                                                                  | Tech                                  |
| ----------- | --------------------------------------------------------------------------- | ------------------------------------- |
| `backend/`  | Laravel application (HTTP API, Inertia server, Reverb, queues, migrations) | PHP 8.3 · Laravel 12 · Sanctum · Reverb |
| `frontend/` | Vue 3 client compiled by Vite (Inertia + Tailwind, all `.vue` pages)       | Vue 3 · Vite 6 · Tailwind 3 · Inertia 2 |

> **Important — runtime coupling.** The Vue client uses **Inertia.js**, which
> means the rendered SPA is delivered by Laravel (`@inertia` in
> `backend/resources/views/app.blade.php`) and every route/form goes through
> Laravel controllers that return `Inertia::render(...)`. Splitting this repo
> into two folders gives you separate codebases, dependency trees, build
> pipelines and `.env` files, but the two halves still talk to each other at
> runtime: the frontend's Vite build writes its manifest, hot file and SSR
> bundle into the backend's `public/` and `bootstrap/ssr/` directories, and
> Laravel serves the resulting HTML. The frontend cannot be deployed to a
> separate domain (e.g. Vercel) without the backend running.

---

## 1. Folder structure

```
kanban/
├── .github/workflows/ci.yml      # CI runs from repo root, defaults to backend/
├── .gitignore                    # repo-level (OS junk only)
├── README.md                     # ← you are here
├── backend/                      # Laravel app
│   ├── app/  bootstrap/  config/  database/  routes/  storage/  tests/
│   ├── docker/  docs/
│   ├── public/                   # Laravel web root (manifest written here)
│   │   ├── build/                # ← Vite manifest + assets land here
│   │   └── hot                   # ← written while `npm run dev` is running
│   ├── resources/
│   │   └── views/                # Blade only (app.blade.php, emails/)
│   ├── .env / .env.example       # server-only config + REVERB_* keys
│   ├── artisan
│   ├── composer.json / composer.lock
│   ├── docker-compose.yml
│   └── phpunit.xml / phpstan.neon / pint.json
└── frontend/                     # Vue + Vite app
    ├── resources/
    │   ├── css/app.css           # Tailwind entry
    │   └── js/                   # All Pages / Components / Layouts / Composables
    │       ├── app.js  bootstrap.js  echo.js  ssr.js
    │       └── Pages/  Components/  Layouts/  Composables/
    ├── .env / .env.example       # only VITE_* values
    ├── jsconfig.json             # `@/*` → resources/js/*, ziggy-js → ../backend/...
    ├── package.json / package-lock.json
    ├── postcss.config.js
    ├── tailwind.config.js        # content paths reach into ../backend/
    ├── vercel.json
    └── vite.config.js            # writes outputs into ../backend/...
```

---

## 2. What changed (and what did NOT change)

**Files moved** (history preserved via `git mv`):

| From                       | To                                  |
| -------------------------- | ----------------------------------- |
| `app/`, `bootstrap/`, `config/`, `database/`, `docker/`, `docs/`, `public/`, `routes/`, `storage/`, `tests/`, `vendor/` | `backend/…`                         |
| `resources/views/`         | `backend/resources/views/`          |
| `artisan`, `composer.json`, `composer.lock`, `phpunit.xml`, `phpstan.neon`, `pint.json`, `docker-compose.yml`, `.env`, `.env.example` | `backend/…` |
| `resources/js/`            | `frontend/resources/js/`            |
| `resources/css/`           | `frontend/resources/css/`           |
| `package.json`, `package-lock.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `jsconfig.json`, `vercel.json`, `node_modules/` | `frontend/…` |

**Files edited** (only paths/configuration, no functional changes):

| File                            | Change                                                                                |
| ------------------------------- | ------------------------------------------------------------------------------------- |
| `frontend/vite.config.js`       | Added `publicDirectory`, `buildDirectory`, `hotFile`, `ssrOutputDirectory` and explicit `refresh` paths so all build outputs land inside `backend/` |
| `frontend/tailwind.config.js`   | Content globs prefixed with `../backend/` for blade views, framework pagination, storage cache |
| `frontend/jsconfig.json`        | `ziggy-js` path → `../backend/vendor/tightenco/ziggy`; dropped `public` from `exclude` |
| `frontend/package.json`         | Added `name: "kanban-frontend"`. Scripts unchanged (`dev`, `build`)                   |
| `frontend/vercel.json`          | `outputDirectory` → `../backend/public/build`                                          |
| `frontend/.env(.example)`       | New file — only `VITE_*` keys                                                          |
| `backend/.env.example`          | Stripped `VITE_*` lines (Vite reads from `frontend/.env` now)                          |
| `backend/.gitignore`            | Removed `/node_modules` line (lives under `frontend/` now)                             |
| `frontend/.gitignore`           | New file — node_modules, .env, vite cache                                              |
| `.gitignore` (repo root)        | New file — OS/IDE junk only                                                            |
| `.github/workflows/ci.yml`      | Added `defaults.run.working-directory: ./backend`; cache paths prefixed with `backend/` |

**Things that did NOT change** (per the brief):
- No controller, model, request, resource, policy, route, migration, factory, seeder, event, listener, mail, notification or service was modified.
- No Vue page, layout, component, composable, store or route was modified.
- API endpoints, validation rules, response shapes and authentication flow are byte-for-byte identical.
- `app.blade.php` is unchanged — `@vite(['resources/js/app.js', ...])` still works because those strings are *manifest keys*, and Vite still emits the manifest with those exact keys (the `input` in `vite.config.js` is unchanged).
- The Sanctum API at `/api/v1/*` (defined in `backend/routes/api.php`) is unchanged. The `useBoardStore.js` composable already calls it via `axios` and continues to do so — no rewrite needed.

---

## 3. Required package / dependency changes

**None.** `composer.json` and `package.json` keep the same dependencies.
The split is purely a folder/config reorganization.

---

## 4. API configuration

The Vue client talks to Laravel through two channels, both of which keep
working untouched:

1. **Inertia (page navigation + forms)** — same-origin requests to
   `web.php` routes. Configured automatically by `@inertiajs/vue3`.
   Authenticated via Laravel's session cookie.
2. **REST API (`/api/v1/*`)** — same-origin XHR via `axios`. Configured in
   `frontend/resources/js/bootstrap.js`:

   ```js
   window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
   window.axios.defaults.withCredentials = true;
   window.axios.defaults.withXSRFToken = true;
   ```

   Because the bundle is served by Laravel (`@vite` from `app.blade.php`),
   the browser origin is the Laravel origin and `axios` requests are
   same-origin. There is no `VITE_API_BASE_URL` to configure for the
   current setup — adding one would require switching the project off
   Inertia, which is a separate (much larger) migration.

3. **Reverb (websockets)** — the Vue echo client reads
   `VITE_REVERB_*` from `frontend/.env`. Those values **must mirror**
   the `REVERB_*` values in `backend/.env`. See
   `frontend/resources/js/echo.js`.

---

## 5. Authentication handling

Unchanged. Two coexisting auth flows:

- **Web / Inertia** — Laravel session cookies + CSRF token (Sanctum's SPA
  mode). All Vue pages use this. Routes live in `backend/routes/web.php`
  and `backend/routes/auth.php`.
- **API / Sanctum tokens** — `backend/routes/api.php`, prefix `/api/v1/`,
  middleware `auth:sanctum`. Endpoints already exist for
  `auth/{register,login,me,logout}` plus the workspaces / boards / lists /
  cards CRUD. No code changes were required here.

The `SANCTUM_STATEFUL_DOMAINS` value in `backend/.env` continues to cover
`localhost:8000` and `127.0.0.1:8000`, which is where Laravel serves the
SPA in development.

---

## 6. Local development

You need three or four terminals depending on whether you use Reverb /
queues. From the repo root:

### Terminal 1 — Laravel server

```bash
cd backend
composer install            # first time only
cp .env.example .env        # first time only
php artisan key:generate    # first time only
php artisan migrate         # first time only
php artisan serve           # http://localhost:8000
```

> WAMP users: you can also point an Apache vhost at
> `c:\wamp64\www\kanban\backend\public` and skip `php artisan serve`.

### Terminal 2 — Vite dev server (HMR)

```bash
cd frontend
npm install                 # first time only
cp .env.example .env        # first time only
npm run dev                 # http://localhost:5173 (proxied by Laravel)
```

While `npm run dev` is running, Vite writes `backend/public/hot` and
Laravel's `@vite()` directive automatically switches to the dev server
URL. Stop `npm run dev` and Laravel falls back to the built manifest.

### Terminal 3 — Reverb (only if you use real-time features)

```bash
cd backend
php artisan reverb:start
```

### Terminal 4 — Queue worker (only if you trigger queued jobs)

```bash
cd backend
php artisan queue:work
```

---

## 7. Build process

```bash
cd frontend
npm ci
npm run build
```

This emits:
- `backend/public/build/manifest.json` + asset chunks (consumed by
  `@vite()` in Blade)
- `backend/bootstrap/ssr/ssr.js` (consumed by Inertia SSR if enabled)

After building, deploy the **backend** folder — the `public/build/`
manifest is part of it.

---

## 8. Deployment process

Two repeatable patterns:

### A. Single-server (recommended, matches the runtime model)

Build the frontend on the CI runner, then ship the backend folder
including the freshly-emitted `public/build/`:

```bash
# on CI / build server
cd frontend && npm ci && npm run build
cd ../backend && composer install --no-dev --optimize-autoloader

# rsync ./backend/ to the server (must include public/build)
rsync -az --delete backend/ user@server:/var/www/kanban/

# on the server
cd /var/www/kanban
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Apache/Nginx document root must point at
`/var/www/kanban/public`.

### B. Two artifacts, same host

Identical to (A) but you keep the frontend repo separate and produce a
build artifact (`public/build/` + `bootstrap/ssr/ssr.js`) that gets
copied into the backend deployment in the release pipeline. Useful if
the frontend and backend live in separate Git repos eventually.

> Vercel / Netlify / Cloudflare Pages deployment of the frontend
> **alone** is not viable while the app uses Inertia — those platforms
> would serve a manifest that has no Laravel server to consume it. To
> deploy the frontend independently you would need to migrate off
> Inertia (Option C in the original split discussion).

---

## 9. Step-by-step migration (already executed)

For posterity, the steps that produced this layout:

1. Stop `php artisan serve`, `php artisan reverb:start`, `npm run dev`
   and any WAMP services holding handles inside the project.
2. From the repo root: `mkdir backend frontend; mkdir frontend/resources`
3. `git mv resources/js frontend/resources/js`
4. `git mv resources/css frontend/resources/css`
5. `git mv package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js jsconfig.json vercel.json frontend/`
6. `Move-Item node_modules frontend/node_modules` (untracked)
7. `git mv app bootstrap config database docker docs public resources routes storage tests artisan composer.json composer.lock docker-compose.yml GETTING_STARTED.md phpstan.neon phpunit.xml pint.json README.md .editorconfig .env.example .gitattributes .gitignore backend/`
8. `Move-Item vendor backend/vendor; Move-Item .env backend/.env` (untracked)
9. `git mv backend/.github .github` (so GitHub Actions still detects it)
10. Rewrite `frontend/vite.config.js`, `frontend/tailwind.config.js`,
    `frontend/jsconfig.json`, `frontend/.env(.example)`,
    `backend/.env.example`, `backend/.gitignore`,
    `.github/workflows/ci.yml` (path adjustments only, no functional
    changes — see "Files edited" above for the exact diffs).
11. `cd frontend && npm run build` to verify the manifest writes to
    `backend/public/build/`.
12. `cd backend && php artisan serve` and visit http://localhost:8000
    to verify every existing flow (login, dashboard, workspaces,
    boards, kanban drag/drop, email templates) still behaves
    identically.

---

## 10. Verification checklist

After you pull this branch and run dev for the first time:

- [ ] `cd backend && composer install` succeeds
- [ ] `cd frontend && npm install` succeeds
- [ ] `cd frontend && npm run build` writes
      `../backend/public/build/manifest.json` and
      `../backend/bootstrap/ssr/ssr.js`
- [ ] `cd backend && php artisan serve` starts without errors
- [ ] Loading http://localhost:8000 redirects to `/login` and renders
      the Vue login page
- [ ] You can register, log in, navigate to a board, drag a card across
      lists, and see the change persist (REST API + Reverb broadcast)
- [ ] `cd backend && php artisan test` passes
- [ ] CI on push runs successfully against the new layout

If any of these fail, the most common cause is that an old
`public/build/` manifest from before the move is being served — delete
`backend/public/build/` and `backend/public/hot`, then `npm run build`
or `npm run dev` again.
