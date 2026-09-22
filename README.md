# QA Release Monitoring

PHP + MySQL dashboard for QA release tracking. The UI is served from **`index.php`**. Data is stored in MySQL and accessed through the **`api/`** JSON endpoints.

## Requirements

- PHP 7.4+ (8.x recommended) with PDO MySQL
- MySQL or MariaDB
- Web server (Apache on InfinityFree, or PHP built-in server for local dev)

## Quick start (local)

1. **Create an empty database** (example name: `qa_release_monitoring`).

2. **Configure credentials:** copy [`api/config.example.php`](api/config.example.php) to `api/config.php` (never commit `api/config.php` — it is in [`.gitignore`](.gitignore)) and set your values:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'qa_release_monitoring');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // your MySQL password if any
   ```

3. **Import SQL (safe — does not wipe existing data):**

   | Goal | Files to import (in order) |
   |------|---------------------------|
   | Structure only (required lookups included) | [`database/schema.sql`](database/schema.sql) → [`database/migrate.sql`](database/migrate.sql) — `migrate.sql` seeds `release_status` so saves work without demo seed |
   | Structure + demo tickets | Above, then optional [`database/seed.sql`](database/seed.sql) |
   | One file in phpMyAdmin | [`database.sql`](database.sql) (schema + migrate + seed combined) |

4. **Run the app:**

   ```bash
   php -S localhost:8000
   ```

   Open [http://localhost:8000/index.php](http://localhost:8000/index.php).

If the database is not connected, the page shows setup instructions only — **no ticket data is displayed**.

## Database tables

| Table | Purpose |
|-------|---------|
| **`qa_data`** | All ticket / change-request rows (new and existing data from the app) |
| **`new_sprint`** | Sprint names created via **New Sprint** or imports |
| **`user`** | Developers and QA (`role` = `Developer` or `QA`) |
| **`release_status`** | Lookup: `Released`, `For release`, `Not in release` (linked from `qa_data.release_status_id`) |
| **`ticket_history`** | Audit log per ticket: `created`, `updated`, `deleted`, `imported`, `transferred` (JSON details + timestamp) |
| **`qa_data.remarks`** | Optional notes and sprint-transfer log lines (TEXT) |

The UI still uses labels like “Change ID” and “Release Status”; only the **MySQL table names** above are used in the schema and PHP.

## Database files (important)

| File | Purpose | Safe to re-run? | Affects existing data? |
|------|---------|-----------------|-------------------------|
| [`database/schema.sql`](database/schema.sql) | Creates tables if missing | Yes | No — no `DROP`, no deletes |
| [`database/migrate.sql`](database/migrate.sql) | Incremental structure updates | Yes | No — only adds/changes schema when you add new `ALTER` lines |
| [`database/seed.sql`](database/seed.sql) | Optional demo data for all four tables | Yes | No — skips rows that already exist (`ON DUPLICATE KEY UPDATE` no-op on `qa_data`) |
| [`database.sql`](database.sql) | schema + migrate + seed in one import | Yes | Same as running the three files above |
| [`database/fresh.sql`](database/fresh.sql) | **Clears all rows**; keeps current tables | Yes | **Yes — empty database**; restores `release_status` lookups (dev only) |
| [`database/reset.sql`](database/reset.sql) | **Drops all tables** | — | **Yes — deletes structure and data** (dev only) |

### First-time install (production or InfinityFree)

1. Create the database in your hosting panel.
2. In phpMyAdmin, select that database.
3. Import **`database/schema.sql`**, then **`database/migrate.sql`**.
4. Optionally import **`database/seed.sql`** if you want demo data.
5. Copy **`api/config.example.php`** to **`api/config.php`** and set credentials (on InfinityFree, `DB_HOST` is usually `sql###.infinityfree.com`, not `localhost`).
6. Upload the project to `htdocs` and open **`index.php`**.

### Update app / schema without losing data

When a new version of this project adds database changes:

1. Back up your database (export from phpMyAdmin).
2. Import **`database/schema.sql`** again (creates any missing tables only).
3. Apply migrations (pick one):
   - **CLI (recommended):** from the project root, `php migrate.php` — runs only pending steps; does not delete rows.
   - **phpMyAdmin:** import **`database/migrate.sql`** (idempotent `ALTER` blocks; safe to re-run).
4. If you already imported **`migrate.sql`** by hand and the app works, run **`php migrate.php sync-legacy`** once so the CLI knows those steps are done (optional; avoids re-running the same SQL later).
5. **Do not** import **`database/fresh.sql`** or **`database/reset.sql`** on production unless you intend to wipe data (`fresh.sql` = empty tables; `reset.sql` = drop tables).
6. **Do not** re-import **`database/seed.sql`** on production unless you only want to fill in *missing* demo rows — existing `qa_data` rows with the same Change ID are left unchanged.

#### `php migrate.php` (Laravel-style)

Requires **`api/config.php`** and base tables from **`schema.sql`**.

| Command | Purpose |
|---------|---------|
| `php migrate.php` | Run pending migrations from **`database/migrations/`** |
| `php migrate.php status` | Show Ran / Pending per file |
| `php migrate.php --pretend` | Dry run (lists pending only) |
| `php migrate.php sync-legacy` | Record all migrations as applied without SQL (after manual **`migrate.sql`**) |

New schema changes: add a new `database/migrations/YYYY_MM_DD_NNNNN_description.php` file (return a callable that runs idempotent SQL) and mirror the same SQL in **`database/migrate.sql`** for hosts without SSH/CLI.

If you still have the **old** table names (`tickets`, `sprints`, `members`), run **`database/reset.sql`** on a dev copy only, then import **`schema.sql`** → **`migrate.sql`** → **`seed.sql`** (or migrate data manually before dropping old tables).

To regenerate demo seed SQL from sample data (developers only):

```bash
node scripts/build-seed.js
```

This refreshes **`database/seed.sql`** and **`database.sql`**.

## InfinityFree deployment

1. Create hosting + MySQL database in the InfinityFree panel.
2. phpMyAdmin: import **`database/schema.sql`**, **`database/migrate.sql`**, and optionally **`database/seed.sql`**.
3. Copy **`api/config.example.php`** to **`api/config.php`** with the panel’s hostname, database name, user, and password.
4. Upload all files (keep `api/`, `assets/`, `includes/`, `index.php`, `.htaccess`). The **`assets/`** folder must be on the server: `index.php` embeds **`assets/css/style.css`** and **`assets/js/app.js`** via PHP (editing those files still updates the UI; static `/assets/...` URLs are not required for the main page).
5. Visit your site URL (`index.php`).

## Project layout

```text
index.php              Main page (PHP checks DB before showing data)
api/                   JSON API (PDO + transactions)
includes/              Shared PHP helpers
assets/css, assets/js  Frontend sources (inlined into index.php at runtime)
database/              SQL scripts (schema, migrate, seed, fresh, reset)
database.sql           Combined safe install for phpMyAdmin
```

## API (no authentication)

- `GET api/data.php` — load tickets, sprints, members
- `GET api/tickets/history.php?id={ticketId}` — action history for a ticket
- `POST api/tickets/create.php`, `update.php`, `delete.php`, `import.php`, `transfer_sprint.php`
- `POST api/sprints/create.php`, `api/members/create.php`

Write operations use **database transactions** (commit on success, rollback on failure).

**Import** upserts by **Change ID** (existing rows are updated). **Export all data** runs in the browser (SheetJS) from the loaded ticket list—no extra API.

## Testing

### Automated API tests (CLI, no Composer)

Requires a running PHP server and a configured `api/config.php` pointing at a database with **schema + migrate** imported (seed optional).

```bash
php -S localhost:8002 -t .
php tests/run_api_tests.php --base-url=http://localhost:8002
```

- Exit code **0** = all scenarios passed; **1** = one or more failures (CI-friendly).
- Tests create rows prefixed with `CH-TEST-*` and clean up the main ticket; see [`tests/run_api_tests.php`](tests/run_api_tests.php).
- Helper-only checks (payload validation, date parsing) run inside the same script via [`tests/test_helpers.php`](tests/test_helpers.php).

### Manual UI checklist

Browser-based scenarios (filters, CRUD, import, disconnected DB): [`tests/TEST_SCENARIOS.md`](tests/TEST_SCENARIOS.md).
