# API Reference

Base URL: **`http://localhost:8000/api`**

All endpoints return JSON. Times are ISO-8601 UTC.

---

## Conventions

### Authentication
Token-based (Laravel Sanctum). Obtain a token via `POST /register` or `POST /login`, then send it on every protected request:

```
Authorization: Bearer <token>
Accept: application/json
```

Tokens expire after 24h. `POST /logout` revokes the current token.

### Roles
Every user has a role: **`admin`**, **`contractor`**, or **`customer`**. Endpoints are authorized per role, and **list results are role-scoped** — the same endpoint returns different rows depending on who calls it (admins see everything; contractors/customers see only their own).

### Response envelope
- **Single resource** → wrapped in `data`:
  ```json
  { "data": { "id": 1, "name": "…" } }
  ```
- **Collections** → `data` + pagination `meta`/`links`:
  ```json
  {
    "data": [ /* … */ ],
    "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
    "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 52 }
  }
  ```
- A few read endpoints (`/user`, `*/options`, `dashboard/stats`) return a bare/custom object by design (noted below).

### Errors
Consistent for all `/api/*` routes regardless of `Accept`:

| Status | Meaning | Body |
|---|---|---|
| `401` | Unauthenticated | `{ "message": "Unauthenticated." }` |
| `403` | Forbidden (role/ownership) | `{ "message": "…" }` |
| `404` | Not found | `{ "message": "…" }` |
| `422` | Validation failed | `{ "message": "…", "errors": { "field": ["…"] } }` |
| `429` | Rate limited (auth routes) | `{ "message": "Too Many Attempts." }` |

### Common list query params
`?page=`, `?per_page=` (1–100), `?sort=<whitelisted col>`, `?dir=asc|desc`, `?search=`, plus per-resource filters.

---

## Auth

### `POST /register`  · public · _throttled 6/min_
Registers a **contractor** or **customer** and creates their profile.

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "contractor",            // "contractor" | "customer" (admin not allowed)
  "company_name": "Bright Solar",  // required when role=contractor
  "phone": "555-1000",             // optional
  "address": "1 Solar Way"         // optional (customer)
}
```
**201** → `{ "user": { id, name, email, role, … }, "token": "…" }`

### `POST /login`  · public · _throttled 6/min_
```json
{ "email": "admin@example.com", "password": "password" }
```
**200** → `{ "user": { … }, "token": "…" }` · **422** on bad credentials.

### `GET /user`  · auth
Current user (bare object, not `data`-wrapped): `{ id, name, email, role, status, last_login_at, created_at }`.

### `POST /logout`  · auth
Revokes the current token. **200** → `{ "message": "Logged out." }`

---

## Projects

### `GET /projects`  · auth · role-scoped
**Filters:** `status` (CSV → whereIn), `contractor_id`, `customer_id`, `region` (via contractor), `min_capacity`, `max_capacity`, `install_from`, `install_to`, `has_application` (`0|1`), `search` (name + address).
**Sort:** `name`, `status`, `capacity_kw`, `install_date`, `created_at` (default `created_at desc`, stable `id` tiebreaker).
Eager-loads contractor/customer + battery count (no N+1).

```
GET /projects?status=submitted,in_review&region=North&sort=name&dir=asc&per_page=20
```

### `POST /projects`  · contractor | admin
Contractors create under their own profile; **admins must pass `contractor_id`**.
```json
{ "name": "Roof Install", "customer_id": 4, "contractor_id": 2, "address": "…", "capacity_kw": 12.5, "install_date": "2026-07-01" }
```
**201** → `{ "data": { … } }`

### `GET /projects/{project}`  · owner | admin
Returns the project with `battery_systems`, `documents`, and `application`.

### `PUT|PATCH /projects/{project}`  · owner contractor | admin
Update `name`, `customer_id`, `address`, `capacity_kw`, `install_date`. (Status is **not** editable here — use the transition endpoint.)

### `DELETE /projects/{project}`  · owner contractor | admin
Blocked (`422`) if the application is `submitted`/`under_review`/`reserved`/`paid`.

### `POST /projects/{project}/transition`  · role-per-edge
Drives the **project state machine**: `draft → submitted → in_review → approved → installed → closed` (+ `rejected` from `in_review`).
```json
{ "to": "in_review", "reason": "optional note" }
```
**200** updated project · **422** illegal edge · **403** wrong role.

### `POST /projects/{project}/documents`  · owner | admin
`multipart/form-data`: `file` (pdf/jpg/jpeg/png, ≤10MB), `type` (string). **201** → document with a signed `download_url`.

---

## Applications

### `GET /applications`  · auth · role-scoped
**Filters:** `status` (CSV), `project_id`, `contractor_id`, `region`, `submitted_from`, `submitted_to`, `search` (project name + contractor company).
**Sort:** `status`, `submitted_at`, `created_at`, `updated_at`.

### `POST /applications`  · project owner (contractor/customer) | admin
Creates the (single) application for a project.
```json
{ "project_id": 7 }
```
**201** → application at status `started`. **422** if the project already has one.

### `GET /applications/{application}`  · owner | admin
Returns `status`, `current_step`, `step_keys`, `steps[]` (each with `fields`, `is_complete`), `documents[]`, `project`.

### `PUT /applications/{application}/steps/{stepKey}`  · owner (contractor/customer) | admin
`stepKey` ∈ `eligibility | system | documents | review`.
```json
{ "data": { "owns_property": true, "utility_provider": "Acme", "average_monthly_bill": 180 }, "complete": true }
```
- `complete: false` → save progress (lenient).
- `complete: true` → strict per-step validation; field errors come back as `data.<field>`.
- Completing `documents` requires ≥1 uploaded document; first save advances the app `started → in_progress`.

### `POST /applications/{application}/submit`  · owner contractor/customer | admin
Requires all steps complete + ≥1 document. `in_progress → submitted`. **422** lists missing steps/documents.

### `POST /applications/{application}/transition`  · role-per-edge (admin/customer)
Drives the **application state machine**:
`started → in_progress → submitted → under_review → reserved → paid` (+ `rejected`, `withdrawn`).
```json
{ "to": "reserved", "incentive_amount": 4200, "reason": "optional" }
```
- `reserved` **requires `incentive_amount`** → schedules a payment + notifies all related parties (queued).
- Customers may `withdraw`; admins drive `under_review/reserved/rejected/paid`.
- **422** illegal edge · **403** wrong role.

### `POST /applications/{application}/documents`  · owner | admin
Same as project documents (multipart `file` + `type`).

### `DELETE /applications/{application}`  · owner contractor | admin
Blocked (`422`) once `submitted` or beyond.

---

## Documents

### `GET /documents/{document}/download`  · _signed URL_ (no token)
Streams the private file. The temporary signed URL (30-min expiry) is provided as `download_url` on each document resource.

### `DELETE /documents/{document}`  · owner of the parent | admin
Deletes the row **and** the stored file. **200** → `{ "message": "Document deleted." }`

---

## Dashboard & Notifications

### `GET /dashboard/stats`  · auth · role-scoped
Custom JSON (grouped counts via SQL, not `data`-wrapped):
```json
{
  "projects":     { "total": 16, "by_status": { "draft": 3, "installed": 2, … } },
  "applications": { "total": 14, "by_status": { … } },
  "incentives":   { "reserved_total": 27742.42, "paid_total": 6294.37, "scheduled_total": 18183.53 },
  "recent_applications": [ /* up to 5 */ ],
  "notifications": { "unread_count": 4 }
}
```

### `GET /notifications`  · auth (own only)
**Filters:** `unread` (`1`), `type`. Paginated. Each: `{ id, type, payload, is_read, read_at, created_at }`.

### `POST /notifications/{notification}/read`  · owner
Marks one read. `403` for another user's notification.

### `POST /notifications/read-all`  · auth
Marks all of the caller's unread. **200** → `{ "marked_read": 3 }`

---

## Admin only

### Users
- `GET /users` — list (filters: `search` name/email, `role`; paginated).
- `PATCH /users/{user}/role` — change role: `{ "role": "contractor" }`. Backfills the matching profile; **422** if you target your own account or send an invalid role.

### Contractors
- `GET /contractors` — list (filters: `search`, `region`, `status`).
- `POST /contractors` — provision a contractor **+ user** (`name`, `email`, `password`, `company_name`, …).
- `GET /contractors/{contractor}` · `PUT|PATCH /contractors/{contractor}` (update `company_name`, `status`, …) · `DELETE` (422 if it has projects).
- `GET /contractors/options` — `[{ id, company_name }]` lightweight picker (**admin**).

### Customers
- `GET /customers` · `POST /customers` (provision customer + user) · `GET|PUT|PATCH|DELETE /customers/{customer}`.
- `GET /customers/options` — `[{ id, full_name, account_email }]` picker (**contractor or admin** — they create projects).

### Audit log
- `GET /audit-logs` — recent entries (filters: `action`, `user_id`, `subject_type`). Each: `{ id, action, subject_type, subject_id, changes, user, created_at }`.

---

## Quick smoke test (curl)

```bash
BASE=http://localhost:8000/api
TOKEN=$(curl -s -X POST $BASE/login -H 'Accept: application/json' \
  -d 'email=admin@example.com&password=password' | php -r 'echo json_decode(file_get_contents("php://stdin"))->token;')

curl -s "$BASE/projects?per_page=5" -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
curl -s "$BASE/dashboard/stats"     -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```
