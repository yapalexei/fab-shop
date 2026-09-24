# fab-shop

Vertical SaaS for quoting, job-tracking, and invoicing for small custom/ornamental/mobile metal fabrication and welding shops (1-8 people) — replacing the QuickBooks-plus-spreadsheets-plus-paper-traveler status quo.

## Tech stack

- Laravel 13 (PHP 8.3+), Inertia.js + React + TypeScript, Tailwind CSS
- PostgreSQL, single database, shared schema
- Pest for testing, Pint for code style

## Local development (Sail)

The app runs via Laravel Sail (Docker), not the host PHP — `.env`'s `DB_HOST=pgsql`/`MAIL_HOST=mailpit` only resolve inside Sail's Docker network, so bare `php artisan` / `npm` on the host will fail to connect. Use `./vendor/bin/sail artisan ...`, `./vendor/bin/sail npm ...`, `./vendor/bin/sail test`, etc. (alias `sail` to `./vendor/bin/sail` for less typing). Bring the stack up with `./vendor/bin/sail up -d`.

Services: `pgsql` (Postgres, matches production, default port 5432), `redis` (queue backend for Horizon), `mailpit` (catches all outgoing mail — inbox UI at `http://localhost:8025`), `horizon`, and `scheduler` (both below). App runs at `http://localhost` (port 80).

The test suite also runs against a real Postgres `testing` database (via `phpunit.xml`), not sqlite — so `./vendor/bin/sail test` needs the `pgsql` container up. Tests force `QUEUE_CONNECTION=sync` and `CACHE_STORE=array` (also in `phpunit.xml`), so they never touch Redis or Horizon.

**Queue worker:** the `horizon` service runs `artisan horizon` as its own container (overriding Sail's default entrypoint, same image) and starts automatically with `./vendor/bin/sail up -d` — quote PDF generation/email (F2) and any future queued work just processes on its own, no manual `queue:work` needed. Dashboard at `http://localhost/horizon` (gated by `HorizonServiceProvider::gate()` outside local env). `QUEUE_CONNECTION=redis` — Horizon only supports Redis.

**Scheduler:** the `scheduler` service runs `artisan schedule:work` the same way (own container, overridden entrypoint) so `routes/console.php`'s `Schedule::command(...)` entries (currently just `app:notify-expired-trials`, daily) actually fire without a real cron. `schedule:work` polls once a minute and re-evaluates every registered schedule; check what's due with `./vendor/bin/sail artisan schedule:list`.

**Reverb** (job board live updates, F3) is the one background piece that still isn't automatic — `./vendor/bin/sail artisan reverb:start` needs its own terminal, port 8080 (exposed in `compose.yaml`). Job board broadcasts use `ShouldBroadcastNow` specifically so they don't *need* a queue worker, but Reverb itself still has to be running to accept the WebSocket connection. Unlike the queue worker, Reverb isn't a Horizon-managed process and there's no equivalent "just works" story for it yet — a `reverb` Sail service would be the natural next step if this becomes a recurring friction point.

`./vendor/bin/sail artisan storage:link` is required once per environment for anything served off the `public` disk (job photos) — quote PDFs don't need it since they're streamed through a controller route instead.

### Demo data

`demo:seed-team` and `demo:seed-multi-shop` build realistic, fully interconnected data for manual testing — a shop with a full team + catalog + an in-progress job (first command), and two isolated shops plus a user who belongs to both, to click through tenant isolation and the shop switcher (second command). Both print login credentials (password `password` for every seeded user) and accept `--fresh` to replace their own data on a rerun.

Both refuse to run unless `APP_MODE=demo` is set (on top of a non-production `APP_ENV`) — two independent gates, so a stray env misconfiguration can't run them somewhere real data lives. `APP_MODE` is separate from `APP_ENV` on purpose: local dev without `APP_MODE=demo` set still refuses to run these.

## Multi-tenancy (critical)

Every tenant-owned table carries a `shop_id`. Tenant isolation is enforced exclusively through the `BelongsToShop` trait + Eloquent global scope, keyed off the authenticated user's *current* shop (`users.current_shop_id`) — never their full membership list. Never add `->where('shop_id', ...)` manually at a call site — the global scope is the only thing that guarantees no cross-tenant leak. Every new tenant-owned model needs a cross-tenant-isolation test (a user from Shop A must not be able to read/write Shop B's records).

Shop membership is many-to-many (`shop_user` pivot) — a user can belong to several shops, a shop can have several users, and each shop has exactly one owner (`shops.owner_id`). Roles (Owner/Admin/Staff) are assigned per shop via Spatie permission's teams feature, keyed on `shop_id`, so the same user can hold different roles in different shops. A member of more than one shop gets a shop switcher in the nav (`ShopSwitchController`, `POST /current-shop/{shop}`) that flips `current_shop_id` after checking membership — nothing else about tenant scoping changes, since everything already keys off that column.

## Current stage

Greenfield build. Feature build order: Foundation → Customers → (Subscription billing / Feature gating, in parallel) → Quote builder → Quote-to-job conversion → Job/traveler board → QuickBooks sync → (Material tracking / Mobile+API / Weld records, in parallel).

See [docs/product-desc.md](docs/product-desc.md) for the full feature dependency table, package list, subscription tier/feature-gating design, and user-flow diagrams — read it before starting work on a new feature area.
