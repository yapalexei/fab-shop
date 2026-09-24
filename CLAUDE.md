# fab-shop

Vertical SaaS for quoting, job-tracking, and invoicing for small custom/ornamental/mobile metal fabrication and welding shops (1-8 people) — replacing the QuickBooks-plus-spreadsheets-plus-paper-traveler status quo.

## Tech stack

- Laravel 13 (PHP 8.3+), Inertia.js + React + TypeScript, Tailwind CSS
- PostgreSQL, single database, shared schema
- Pest for testing, Pint for code style

## Local development (Sail)

The app runs via Laravel Sail (Docker), not the host PHP — `.env`'s `DB_HOST=pgsql`/`MAIL_HOST=mailpit` only resolve inside Sail's Docker network, so bare `php artisan` / `npm` on the host will fail to connect. Use `./vendor/bin/sail artisan ...`, `./vendor/bin/sail npm ...`, `./vendor/bin/sail test`, etc. (alias `sail` to `./vendor/bin/sail` for less typing). Bring the stack up with `./vendor/bin/sail up -d`.

Services: `pgsql` (Postgres, matches production, default port 5432), `redis` (queue backend for Horizon), `mailpit` (catches all outgoing mail — inbox UI at `http://localhost:8025`), `horizon`, `scheduler`, and `reverb` (all below). App runs at `http://localhost` (port 80).

The test suite also runs against a real Postgres `testing` database (via `phpunit.xml`), not sqlite — so `./vendor/bin/sail test` needs the `pgsql` container up. Tests force `QUEUE_CONNECTION=sync` and `CACHE_STORE=array` (also in `phpunit.xml`), so they never touch Redis or Horizon.

**Queue worker:** the `horizon` service runs `artisan horizon` as its own container (overriding Sail's default entrypoint, same image) and starts automatically with `./vendor/bin/sail up -d` — quote PDF generation/email (F2) and any future queued work just processes on its own, no manual `queue:work` needed. Dashboard at `http://localhost/horizon` (gated by `HorizonServiceProvider::gate()` outside local env). `QUEUE_CONNECTION=redis` — Horizon only supports Redis.

**Scheduler:** the `scheduler` service runs `artisan schedule:work` the same way (own container, overridden entrypoint) so `routes/console.php`'s `Schedule::command(...)` entries (currently just `app:notify-expired-trials`, daily) actually fire without a real cron. `schedule:work` polls once a minute and re-evaluates every registered schedule; check what's due with `./vendor/bin/sail artisan schedule:list`.

**Reverb** (job board live updates, F3) runs the same way — its own `reverb` container (entrypoint overridden to `artisan reverb:start`), port 8080. Job board broadcasts use `ShouldBroadcastNow` specifically so they don't *need* a queue worker, but Reverb itself still has to be running to accept the WebSocket connection, which it now is by default.

Splitting Reverb into its own container broke server-to-server broadcasting until it was caught and fixed: `REVERB_HOST=localhost` is right for the *browser* (it connects through the port Docker forwards to the host), but this app also has to push events to Reverb itself when something happens (`broadcast(...)`), and from inside the `laravel.test` container "localhost" no longer means anything - Reverb is a separate container now. Fixed with a second env var, `REVERB_INTERNAL_HOST=reverb` (the Docker service name), which `config/broadcasting.php`'s `reverb` connection uses for its `host` option instead of `REVERB_HOST`; the browser-facing `VITE_REVERB_HOST` is untouched. Because `JobBoardUpdated` is `ShouldBroadcastNow`, a broken connection here throws mid-request - the status *does* still save (the DB update happens before the broadcast call), but the request comes back as a 500. If that happens again, check `storage/logs/laravel.log` for a Pusher/cURL connection error before assuming the state machine itself is broken.

`./vendor/bin/sail artisan storage:link` is required once per environment for anything served off the `public` disk (job photos) — quote PDFs don't need it since they're streamed through a controller route instead.

### Demo data

`demo:seed-team` and `demo:seed-multi-shop` build realistic, fully interconnected data for manual testing — a shop with a full team + catalog + an in-progress job, plus six months of invoiced jobs and open/stale/lost quotes so the dashboard has real numbers (first command), and two isolated shops plus a user who belongs to both, to click through tenant isolation and the shop switcher (second command). Both print login credentials (password `password` for every seeded user) and accept `--fresh` to replace their own data on a rerun.

Both refuse to run unless `APP_MODE=demo` is set (on top of a non-production `APP_ENV`) — two independent gates, so a stray env misconfiguration can't run them somewhere real data lives. `APP_MODE` is separate from `APP_ENV` on purpose: local dev without `APP_MODE=demo` set still refuses to run these.

### QuickBooks Online sync (F4)

Built without real Intuit credentials - `QUICKBOOKS_CLIENT_ID`/`QUICKBOOKS_CLIENT_SECRET` are blank in `.env`/`.env.example`, so the OAuth connect button currently redirects to a real Intuit URL that can't complete the handshake. Everything downstream of that (token exchange, refresh-on-expiry, customer dedupe, invoice push/retry) is built and tested against `Http::fake()` in `tests/Feature/QuickBooksConnectionTest.php` and `QuickBooksInvoiceSyncTest.php` - covers the three acceptance cases from `docs/product-desc.md`'s F4 section (token expiry mid-sync, dedupe not duplicate, retry not silently drop) - but has never round-tripped against a real sandbox company.

To wire up real credentials: register a free app at [developer.intuit.com](https://developer.intuit.com), grab its sandbox Client ID/Secret, set `QUICKBOOKS_CLIENT_ID`/`QUICKBOOKS_CLIENT_SECRET` in `.env`, and add `http://localhost/quickbooks/callback` as the app's redirect URI in Intuit's dashboard (must match `QUICKBOOKS_REDIRECT_URI` exactly). `QUICKBOOKS_ENVIRONMENT=sandbox` talks to a different API base URL than production, not just a header/param switch - flip it deliberately, not as an afterthought.

`App\Services\QuickBooks\QuickBooksClient` is built directly on Laravel's `Http` facade rather than the official `quickbooks/v3-php-sdk` - that package has its own HTTP internals that bypass `Http::fake()`, which would have made the acceptance-case tests much harder to write without a live sandbox company.

`Job->quickbooks_sync_error` holds the last failure message if a push failed - check it (or the job in Horizon's Failed tab once `$tries` is exhausted) before assuming QuickBooks sync silently isn't happening.

## Multi-tenancy (critical)

Every tenant-owned table carries a `shop_id`. Tenant isolation is enforced exclusively through the `BelongsToShop` trait + Eloquent global scope, keyed off the authenticated user's *current* shop (`users.current_shop_id`) — never their full membership list. Never add `->where('shop_id', ...)` manually at a call site — the global scope is the only thing that guarantees no cross-tenant leak. Every new tenant-owned model needs a cross-tenant-isolation test (a user from Shop A must not be able to read/write Shop B's records).

Shop membership is many-to-many (`shop_user` pivot) — a user can belong to several shops, a shop can have several users, and each shop has exactly one owner (`shops.owner_id`). Roles (Owner/Admin/Staff) are assigned per shop via Spatie permission's teams feature, keyed on `shop_id`, so the same user can hold different roles in different shops. A member of more than one shop gets a shop switcher in the nav (`ShopSwitchController`, `POST /current-shop/{shop}`) that flips `current_shop_id` after checking membership — nothing else about tenant scoping changes, since everything already keys off that column.

## Current stage

Greenfield build. Feature build order: Foundation → Customers → (Subscription billing / Feature gating, in parallel) → Quote builder → Quote-to-job conversion → Job/traveler board → QuickBooks sync → (Material tracking / Mobile+API / Weld records, in parallel).

See [docs/product-desc.md](docs/product-desc.md) for the full feature dependency table, package list, subscription tier/feature-gating design, and user-flow diagrams — read it before starting work on a new feature area.
