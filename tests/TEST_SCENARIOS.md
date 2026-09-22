# Manual test scenarios — QA Release Monitoring

Use this checklist in a browser after the database is configured and SQL is imported. Automated API coverage lives in [`run_api_tests.php`](run_api_tests.php) (scenario numbers cross-referenced below).

## Environment setup

1. Copy [`api/config.example.php`](../api/config.example.php) to `api/config.php` and set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
2. Import SQL **in order**:
   - [`database/schema.sql`](../database/schema.sql)
   - [`database/migrate.sql`](../database/migrate.sql) — includes required `release_status` rows
   - Optional: [`database/seed.sql`](../database/seed.sql) for demo data (51 tickets)
3. Start the server: `php -S localhost:8002 -t .`
4. Open [http://localhost:8002/index.php](http://localhost:8002/index.php).

## Disconnected database

| Step | Action | Expected |
|------|--------|----------|
| D1 | Set wrong password in `api/config.php` or stop MySQL | Page shows disconnect banner / setup message |
| D2 | Observe main content | No KPI cards, chart, filters, or ticket table |
| D3 | Try to use Add Ticket (if visible) | Save fails; UI reflects disconnected state after POST |

## Connected database (with seed)

| Step | Action | Expected |
|------|--------|----------|
| C1 | Load `index.php` with valid config + seed imported | ~51 tickets; KPIs and chart populate |
| C2 | Check sprint / owner / QA dropdowns | Values from seed and lookups present |
| C3 | Refresh page | Same data loads from API (no client-only persistence) |

## Filters

| Step | Action | Expected |
|------|--------|----------|
| F1 | Change **Change Stage** filter | Table rows match selected stage |
| F2 | Change **Change Status** filter | Table rows match |
| F3 | Change **Change Type** filter | Table rows match |
| F4 | Change **Release Status** filter | Table rows match |
| F5 | Set **Created** date range | Only tickets in range shown |
| F6 | Type in **Title** search | Substring match on title |
| F7 | Combine multiple filters | Intersection of criteria |

## Sorting

| Step | Action | Expected |
|------|--------|----------|
| S1 | Click column headers (Change ID, Title, dates, etc.) | Rows reorder; toggle asc/desc where supported |

## Pagination

| Step | Action | Expected |
|------|--------|----------|
| Pg1 | With 51+ seed tickets, default **10 rows per page** | Footer shows “Showing 1–10 of N”; only 10 table rows |
| Pg2 | Change rows per page to 25 / 50 / 100 | Table and footer update |
| Pg3 | **Previous** / **Next** | Page changes; buttons disable at first/last page |
| Pg4 | Apply a filter | Resets to page 1 |

## Tickets — CRUD

| Step | Action | Expected | Auto ref |
|------|--------|----------|----------|
| T1 | **Add Ticket** — fill required fields, save | New row appears; unique Change ID | 5 |
| T2 | **View** ticket | Read-only modal with correct fields | — |
| T3 | **Edit** ticket — change Title / Release Status | Updates persist after refresh | 6 |
| T4 | **Edit** — Change ID field | Disabled (cannot change Change ID) | — |
| T5 | **Add/Edit** — **Change Stage** and **Change Status** in modal | Values save and persist after refresh | — |
| T6 | **Delete** ticket | Row removed; KPIs update | 10 |
| T7 | **History** (⋮ menu) | Lists create/update/import/delete/transferred actions with timestamps and field changes | 12, 13 |
| T8 | **Edit** — **Transfer Sprint** to another sprint with note | Sprint updates; remarks append transfer line; History shows Transferred | 13 |
| T9 | **Remarks / Notes** on save (no transfer) | Text persists after refresh | — |

## Sprint and members

| Step | Action | Expected | Auto ref |
|------|--------|----------|----------|
| M1 | **New Sprint** — create name | Appears in sprint dropdown on add/edit | 3 |
| M2 | **New Dev-QA** — add Developer and QA | Names appear in owner/QA dropdowns | 4 |

## Import (XLSX)

| Step | Action | Expected | Auto ref |
|------|--------|----------|----------|
| I1 | Download template / **Export All Existing Data** | XLSX columns match app fields; export includes all loaded tickets |
| I2 | Upload valid XLSX with new Change IDs | New rows added; alert shows insert count | 9 |
| I3 | Upload row with existing **Change ID** | Row overwritten; alert shows updated count; no duplicate IDs | 9 |

## Negative / validation

| Step | Action | Expected | Auto ref |
|------|--------|----------|----------|
| N1 | Add ticket with empty **Title** | Validation error; not saved | 8 |
| N2 | Add ticket with duplicate **Change ID** | Friendly error message | 7 |
| N3 | Add ticket with missing required fields | Error; no partial save | 8 |

## Persistence

| Step | Action | Expected |
|------|--------|----------|
| P1 | Create or edit a ticket, hard refresh (Ctrl+F5) | Changes still in database |
| P2 | Open in second browser tab | Same data from API |

## API smoke (optional, no browser)

```bash
php -S localhost:8002 -t .
php tests/run_api_tests.php --base-url=http://localhost:8002
```

Exit code `0` means all automated scenarios passed.

## Sign-off

| Area | Tester | Date | Pass |
|------|--------|------|------|
| Disconnected DB | | | |
| Connected + seed | | | |
| Filters / sort | | | |
| CRUD | | | |
| Sprint / members | | | |
| Import | | | |
| Negative cases | | | |
