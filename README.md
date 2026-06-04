# SolarIncentives — Clean-Energy Incentive Platform

A full-stack platform where **contractors** manage solar projects, **customers (applicants)** complete a
multi-step incentive application, and **admins** review projects, drive status workflows, and trigger payments.

- **`/api`** — Laravel 11 JSON API (Sanctum token auth, MySQL, queued jobs, notifications)
- **`/web`** — Nuxt 3 SPA (TypeScript, Pinia, Tailwind, VeeValidate + Zod)

📖 **API docs:** interactive **Swagger UI** at `http://localhost:8000/api/documentation` (OpenAPI annotations on every endpoint), or the written [`API.md`](./API.md).

---

## Stack

| | |
|---|---|
| Backend | Laravel 11, PHP 8.2+, Sanctum, MySQL 8 |
| Frontend | Nuxt 3, Vue 3 `<script setup>`, TypeScript, Pinia, Tailwind, VeeValidate + Zod |
| Tooling | Pint (PHP), ESLint/Prettier (Nuxt), PHPUnit (78 feature tests) |

---

## Prerequisites

- **PHP 8.2+** and **Composer**
- **Node 20+** and **npm**
- **MySQL 8** (running locally)

---

## Quick start (≈ 5 minutes)

### 1. Database

MySQL `root` is often socket-only, so create a dedicated user + the two databases:

```bash
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS laravel_project          CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS laravel_project_testing  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'laravel'@'%' IDENTIFIED BY 'StrongPass123!';
GRANT ALL PRIVILEGES ON laravel_project.*         TO 'laravel'@'%';
GRANT ALL PRIVILEGES ON laravel_project_testing.* TO 'laravel'@'%';
FLUSH PRIVILEGES;
SQL
```

### 2. API (`/api`)

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate

# point .env at the DB user created above
#   DB_USERNAME=laravel
#   DB_PASSWORD=StrongPass123!

php artisan migrate --seed        # schema + demo data
php artisan storage:link          # (optional) for public assets
php artisan serve --port=8000     # API on http://localhost:8000
```

### 3. Queue worker (needed for notifications & scheduled payments)

In a **second terminal** — status-transition side effects run on the queue:

```bash
cd api && php artisan queue:work
```

> Prefer no worker in dev? Set `QUEUE_CONNECTION=sync` in `api/.env` and jobs run inline.

### 4. Web (`/web`)

In a **third terminal**:

```bash
cd web
npm install
npm run dev                       # SPA on http://localhost:3000
```

### 5. Open the app

**http://localhost:3000** → you'll land on the login page.

> The frontend calls the API at `http://localhost:8000/api`. If you run the API on another
> port, set `NUXT_PUBLIC_API_BASE` in `web/.env` accordingly.

---

## Demo login

The seeder creates a known admin (all seeded users share the password `password`):

| Role | Email | Password |
|---|---|---|
| **Admin** | `admin@example.com` | `password` |
| Contractor / Customer | _(random seeded emails — view them on the admin **Users** page)_ | `password` |

You can also **register** a new contractor or customer from the sign-up page.

---

## What you can do

- **Admin** — review/approve projects & applications, manage contractors/customers, change user roles, view the audit log, see aggregate stats.
- **Contractor** — create & manage their own projects, submit applications, upload documents.
- **Customer (applicant)** — complete the multi-step incentive application, upload documents, track progress.

Key flows to try (as admin): Projects → open one → drive its status; Applications → open one → move it to `under_review` → `reserved` (schedules a payment + notifies everyone related); **Users** → change a role; **Audit** → see the trail.

---

## Useful commands

```bash
# Backend
cd api
php artisan test                 # 78 feature tests
./vendor/bin/pint                # format PHP
./vendor/bin/pint --test         # check formatting

# Frontend
cd web
npm run lint                     # ESLint
npm run build                    # production build (also catches type/SSR issues)
npx nuxi typecheck               # TypeScript
```

---

## Architecture notes

- **Auth** — Sanctum bearer tokens (24h expiry); rate-limited login/register; role-based (`admin`/`contractor`/`customer`).
- **Authorization** — Policies per resource; results are **role-scoped** (the same list endpoint returns different rows per role).
- **Validation** — every write goes through a **Form Request** class (no inline validation); the Nuxt forms mirror those rules with Zod.
- **List endpoints** — pagination + multi-field filters + whitelisted sort (stable tiebreaker) + search, all **eager-loaded (no N+1)**.
- **Status workflows** — two server-enforced state machines (`StatusTransitionManager`); illegal transitions → `422`, wrong role → `403`; every transition writes an **audit log** atomically.
- **Background work** — `ProcessApplicationTransition` (idempotent queued job) schedules payments and notifies all related parties; one **queued email** (`IncentiveReservedNotification`).
- **Errors** — consistent JSON envelope for all `/api/*` responses; stack traces gated by `APP_DEBUG`.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `Access denied for 'root'@'localhost'` | `root` is socket-only — use the `laravel` user from step 1 (in `.env`, phpMyAdmin, etc.). |
| **No notifications appear** | Run `php artisan queue:work` (jobs create notifications); or set `QUEUE_CONNECTION=sync`. |
| Login / API calls fail in the browser | Ensure the API is on **:8000** (matches `NUXT_PUBLIC_API_BASE`) and `FRONTEND_URL=http://localhost:3000` in `api/.env` (CORS). |
| Emails | `MAIL_MAILER=log` by default → check `api/storage/logs/laravel.log` (or point to Mailhog). |
| Tests can't connect | They use the separate `laravel_project_testing` DB created in step 1. |

---

## One-command dev (optional)

From the repo root (installs `concurrently` once) — runs the API and web together:

```bash
npm install
npm run dev          # API :8000 + web :3000  (still start `queue:work` separately)
```
