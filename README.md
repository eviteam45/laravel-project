# Nuxt 3 + Laravel 11 Monorepo

A monorepo containing a Laravel 11 JSON API and a Nuxt 3 frontend, authenticated
with **Laravel Sanctum API (bearer) tokens**.

```
.
├── api/   → Laravel 11 API (Sanctum tokens, MySQL)
└── web/   → Nuxt 3 frontend
```

## Requirements

- PHP 8.2+ and Composer
- Node 20+ and npm
- A running MySQL server

## Setup

### 1. API (`api/`)

```bash
cd api
cp .env.example .env          # already done on first scaffold
composer install
php artisan key:generate      # if APP_KEY is empty
```

Create the database and configure credentials in `api/.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_project
DB_USERNAME=root
DB_PASSWORD=
```

```sql
CREATE DATABASE laravel_project;
```

Run migrations:

```bash
php artisan migrate
```

### 2. Web (`web/`)

```bash
cd web
cp .env.example .env          # already done on first scaffold
npm install
```

`NUXT_PUBLIC_API_BASE` defaults to `http://localhost:8000/api`.

## Running

From the repo root (installs `concurrently` once):

```bash
npm install
npm run dev          # starts API on :8000 and web on :3000 together
```

Or run each app separately:

```bash
# Terminal 1 — API
cd api && php artisan serve --port=8000

# Terminal 2 — web
cd web && npm run dev
```

- Frontend: http://localhost:3000
- API: http://localhost:8000/api

## API endpoints

| Method | Path         | Auth         | Description                          |
|--------|--------------|--------------|--------------------------------------|
| POST   | `/register`  | public       | Create a user, returns a token       |
| POST   | `/login`     | public       | Authenticate, returns a token        |
| GET    | `/user`      | bearer token | Current authenticated user           |
| POST   | `/logout`    | bearer token | Revoke the current token             |

### Auth flow

`register`/`login` return `{ user, token }`. The Nuxt frontend stores the token
in a cookie (`composables/useAuth.ts`) and attaches it as
`Authorization: Bearer <token>` on every request (`composables/useApi.ts`).

CORS is restricted to `FRONTEND_URL` (default `http://localhost:3000`) in
`api/config/cors.php`.
