# fab-shop

Vertical SaaS for quoting, job-tracking, and invoicing for small custom/ornamental/mobile metal fabrication and welding shops (1-8 people) — replacing the QuickBooks-plus-spreadsheets-plus-paper-traveler status quo.

## Tech stack

- Laravel 13 (PHP 8.3+), Inertia.js + React + TypeScript, Tailwind CSS
- PostgreSQL, single database, shared schema
- Pest for testing, Pint for code style

## Local development (Sail)

The app runs via Laravel Sail (Docker), not the host PHP — `.env`'s `DB_HOST=pgsql`/`MAIL_HOST=mailpit` only resolve inside Sail's Docker network, so bare `php artisan` / `npm` on the host will fail to connect. Use `./vendor/bin/sail artisan ...`, `./vendor/bin/sail npm ...`, `./vendor/bin/sail test`, etc. (alias `sail` to `./vendor/bin/sail` for less typing). Bring the stack up with `./vendor/bin/sail up -d`.

Services: `pgsql` (Postgres, matches production, default port 5432) and `mailpit` (catches all outgoing mail — inbox UI at `http://localhost:8025`). App runs at `http://localhost` (port 80).

The test suite also runs against a real Postgres `testing` database (via `phpunit.xml`), not sqlite — so `./vendor/bin/sail test` needs the `pgsql` container up.

Two background processes aren't started automatically and need their own terminal when testing anything that depends on them:
- `./vendor/bin/sail artisan queue:work` — quote PDF generation/email (F2) queue through the `database` queue connection; nothing sends until a worker is running.
- `./vendor/bin/sail artisan reverb:start` — the job board's live updates (F3) over Reverb, port 8080 (also exposed in `compose.yaml`). Job board broadcasts use `ShouldBroadcastNow` specifically so they don't *need* a worker, but Reverb itself still has to be running to accept the WebSocket connection.

`./vendor/bin/sail artisan storage:link` is required once per environment for anything served off the `public` disk (job photos) — quote PDFs don't need it since they're streamed through a controller route instead.

## Multi-tenancy (critical)

Every tenant-owned table carries a `shop_id`. Tenant isolation is enforced exclusively through the `BelongsToShop` trait + Eloquent global scope, keyed off the authenticated user's *current* shop (`users.current_shop_id`) — never their full membership list. Never add `->where('shop_id', ...)` manually at a call site — the global scope is the only thing that guarantees no cross-tenant leak. Every new tenant-owned model needs a cross-tenant-isolation test (a user from Shop A must not be able to read/write Shop B's records).

Shop membership is many-to-many (`shop_user` pivot) — a user can belong to several shops, a shop can have several users, and each shop has exactly one owner (`shops.owner_id`). Roles (Owner/Admin/Staff) are assigned per shop via Spatie permission's teams feature, keyed on `shop_id`, so the same user can hold different roles in different shops.

## Current stage

Greenfield build. Feature build order: Foundation → Customers → (Subscription billing / Feature gating, in parallel) → Quote builder → Quote-to-job conversion → Job/traveler board → QuickBooks sync → (Material tracking / Mobile+API / Weld records, in parallel).

See [docs/product-desc.md](docs/product-desc.md) for the full feature dependency table, package list, subscription tier/feature-gating design, and user-flow diagrams — read it before starting work on a new feature area.
