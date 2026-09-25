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

**Scheduler:** the `scheduler` service runs `artisan schedule:work` the same way (own container, overridden entrypoint) so `routes/console.php`'s `Schedule::command(...)` entries (currently just `app:notify-expired-trials`, hourly - a trial ends 14 x 24h after signup, at any time of day) actually fire without a real cron. `schedule:work` polls once a minute and re-evaluates every registered schedule; check what's due with `./vendor/bin/sail artisan schedule:list`.

**Reverb** (job board live updates, F3) runs the same way — its own `reverb` container (entrypoint overridden to `artisan reverb:start`), port 8080. Job board broadcasts use `ShouldBroadcastNow` specifically so they don't *need* a queue worker, but Reverb itself still has to be running to accept the WebSocket connection, which it now is by default.

Splitting Reverb into its own container broke server-to-server broadcasting until it was caught and fixed: `REVERB_HOST=localhost` is right for the *browser* (it connects through the port Docker forwards to the host), but this app also has to push events to Reverb itself when something happens (`broadcast(...)`), and from inside the `laravel.test` container "localhost" no longer means anything - Reverb is a separate container now. Fixed with a second env var, `REVERB_INTERNAL_HOST=reverb` (the Docker service name), which `config/broadcasting.php`'s `reverb` connection uses for its `host` option instead of `REVERB_HOST`; the browser-facing `VITE_REVERB_HOST` is untouched. Because `JobBoardUpdated` is `ShouldBroadcastNow`, a broken connection here throws mid-request - the status *does* still save (the DB update happens before the broadcast call), but the request comes back as a 500. If that happens again, check `storage/logs/laravel.log` for a Pusher/cURL connection error before assuming the state machine itself is broken.

`./vendor/bin/sail artisan storage:link` is required once per environment for anything served off the `public` disk (job photos) — quote PDFs don't need it since they're streamed through a controller route instead.

### Demo data

`demo:seed-team` and `demo:seed-multi-shop` build realistic, fully interconnected data for manual testing — a shop with a full team + catalog (incl. tooling - the same 1/4in bit for steel and for aluminum) + an in-progress job, plus six months of invoiced jobs and open/stale/lost quotes so the dashboard has real numbers (first command), and two isolated shops plus a user who belongs to both, to click through tenant isolation and the shop switcher (second command). Both print login credentials (password `password` for every seeded user) and accept `--fresh` to replace their own data on a rerun.

Both refuse to run unless `APP_MODE=demo` is set (on top of a non-production `APP_ENV`) — two independent gates, so a stray env misconfiguration can't run them somewhere real data lives. `APP_MODE` is separate from `APP_ENV` on purpose: local dev without `APP_MODE=demo` set still refuses to run these.

### QuickBooks Online sync (F4)

Built without real Intuit credentials - `QUICKBOOKS_CLIENT_ID`/`QUICKBOOKS_CLIENT_SECRET` are blank in `.env`/`.env.example`, so the OAuth connect button currently redirects to a real Intuit URL that can't complete the handshake. Everything downstream of that (token exchange, refresh-on-expiry, customer dedupe, invoice push/retry) is built and tested against `Http::fake()` in `tests/Feature/QuickBooksConnectionTest.php` and `QuickBooksInvoiceSyncTest.php` - covers the three acceptance cases from `docs/product-desc.md`'s F4 section (token expiry mid-sync, dedupe not duplicate, retry not silently drop) - but has never round-tripped against a real sandbox company.

To wire up real credentials: register a free app at [developer.intuit.com](https://developer.intuit.com), grab its sandbox Client ID/Secret, set `QUICKBOOKS_CLIENT_ID`/`QUICKBOOKS_CLIENT_SECRET` in `.env`, and add `http://localhost/quickbooks/callback` as the app's redirect URI in Intuit's dashboard (must match `QUICKBOOKS_REDIRECT_URI` exactly). `QUICKBOOKS_ENVIRONMENT=sandbox` talks to a different API base URL than production, not just a header/param switch - flip it deliberately, not as an afterthought.

`App\Services\QuickBooks\QuickBooksClient` is built directly on Laravel's `Http` facade rather than the official `quickbooks/v3-php-sdk` - that package has its own HTTP internals that bypass `Http::fake()`, which would have made the acceptance-case tests much harder to write without a live sandbox company.

`Job->quickbooks_sync_error` holds the last failure message if a push failed - check it (or the job in Horizon's Failed tab once `$tries` is exhausted) before assuming QuickBooks sync silently isn't happening.

## Multi-tenancy (critical)

Every tenant-owned table carries a `shop_id`. Tenant isolation is enforced exclusively through the `BelongsToShop` trait + Eloquent global scope, keyed off the authenticated user's *current* shop (`users.current_shop_id`) — never their full membership list. Never add `->where('shop_id', ...)` manually at a call site — the global scope is the only thing that guarantees no cross-tenant leak. Every new tenant-owned model needs a cross-tenant-isolation test (a user from Shop A must not be able to read/write Shop B's records).

Shop membership is many-to-many (`shop_user` pivot) — a user can belong to several shops, a shop can have several users, and each shop has exactly one owner (`shops.owner_id`). Roles (Owner/Admin/Staff) are assigned per shop via Spatie permission's teams feature, keyed on `shop_id`, so the same user can hold different roles in different shops. A member of more than one shop gets a shop switcher in the nav (`ShopSwitchController`, `POST /current-shop/{shop}`) that flips `current_shop_id` after checking membership — nothing else about tenant scoping changes, since everything already keys off that column.

## Permissions (per shop)

Access is granted per resource and action: permission names are `{resource}.{action}` (e.g. `quotes.delete`, `jobs.cancel`, `financials.view`), defined in `App\Enums\ShopResource` (resources, their actions, and a plain description of what each action really allows). `App\Support\ShopPermissions` holds the full list and each role's out-of-the-box grants (`defaultsFor()`); `seedRoles($shop)` creates a shop's roles with those defaults - called at signup, by the demo commands, and by the test helper. Each permission is also a Gate ability of the same name, so routes use `can:quotes.delete` middleware; resource controllers get theirs from the `AuthorizesShopResource` trait (edit pages need only `view` and render read-only without `update`).

Resolution (`User::shopPermissions()`): the Owner role has everything → otherwise the role's grants in the current shop → then per-member overrides on `shop_user.permission_overrides` (true = allow, false = deny, absent = inherit). Any action on a resource implies `view` of it. Spatie's own `Gate::before` permission check is **disabled** (`permission.register_permission_check_method = false`) - left on, it approved anything the role granted before our gates ran, silently ignoring per-member denies.

Who can change access: editing *role* defaults is Owner-only (`manage-shop` gate, never delegable). Per-member access needs `team.manage-access` (Admins by default), with guard rails in `TeamMemberAccessController`/`UpdateTeamMemberAccessRequest`: nobody edits the owner or themselves; only the owner changes roles or edits an Admin; a non-owner can only *allow* permissions they hold themselves. Adding a permission = add the action to `ShopResource`, decide `defaultsFor()`, and add a migration that calls `ShopPermissions::seedRoles()` for existing shops (it only adds grants, never revokes).

Deletes that would orphan history are refused with a flash `error` instead of hitting the database's restrict constraint (a customer with quotes/jobs; a quote that became a job - archive it instead). Catalog deletes are fine: quote lines keep their description and price, only the link is nulled.

Gate financial data on the server, not just in the UI - e.g. the dashboard omits the `financials` prop entirely without `financials.view`, since Inertia props are readable in page source. `SetPermissionsTeamFromCurrentShop` must run before `HandleInertiaRequests` (it does, in `bootstrap/app.php`) so the shared `auth.permissions` list is resolved against the current shop; the frontend checks it with `useCan()` (`resources/js/lib/permissions.ts`) purely to hide controls.

## Material types and tooling

`material_types` are per-shop records: every shop gets the built-ins in `App\Support\MaterialTypes::DEFAULTS` (via a `Shop::created` hook, so signup, demo data and test factories all have them; `is_default`, can't be renamed or deleted) and may add custom ones up to `config('shop.max_custom_material_types')` (20). Names are unique per shop case-insensitively (a `lower(name)` index). A catalog `Material` has one optional type; a `Tool` is rated for one or more (`material_type_tool`). A type still used by a tool or material can't be deleted - the controller explains, and the FK (no cascade) backs it up. The quote builder warns (advisory, client-side) when a tooling line's tool is rated for none of the quote's material types. To add a built-in, append to `DEFAULTS` and run `MaterialTypes::seedDefaults()` for every shop in a migration (it's idempotent).

## Job board moves

Every status change - button or drag - goes through `JobMoveDialog` for confirmation first. Drag-and-drop (`@dnd-kit/core`, drag handle only so card links and touch-scrolling still work) is a per-shop setting, `shops.job_board_drag_enabled`, off by default (`config('shop.defaults.job_board_drag_enabled')`); the board only enables it for users with `jobs.update`. Moving needs `jobs.update`, cancelling `jobs.cancel` - checked in `JobStatusController` since it depends on the target status.

## Material & scrap tracking (F5)

`JobMaterialUsage` is the actual-consumption ledger, deliberately separate from `QuoteLineItem` (the estimate a job's price is fixed to - see `Job::total`'s own note): it only ever grows, so reversing a job's status never erases the cost of what was already used or scrapped, per the F3 design decision this fulfills. `type` (`App\Enums\MaterialUsageType`: `Used`/`Scrap`) keeps waste visible on its own rather than folded into "used." `Job` gets computed (never stored) `estimated_material_cost` (sum of the quote's `material`-type lines' `cost` - shop's cost, pre-markup, not what the customer is charged), `actual_material_cost`, `scrap_cost`, and `material_cost_variance` (actual + scrap - estimated; positive means the job cost more material than quoted).

Gated by the `material-tracking` feature (`Feature::MaterialTracking`, now `isShipped() => true` - Start-up doesn't include it, Pro does, trial gets everything) on top of `job-board` and `jobs.update` (no separate permission - logging usage is part of working the job, same scoping as job photos). `JobController::show()` omits the `materialTracking` prop entirely for a shop without the feature, same "don't send what they're not entitled to" rule the dashboard's `financials` prop follows - it's not just hidden client-side. Logging or removing an entry also writes a normal `activity()` log entry on the job, so it shows up interleaved with status changes in the existing History panel - unlike job photos, which don't log.

The material picker's "suggested cost" is the material's cheapest known `businessPartners` price, purely a starting point - always overridable, since the entire point of tracking *actual* cost is that it can differ from any predicted default.

## CSV import (F9)

One mechanism shared by seven resources (Customers, Materials, Services, Business Partners, Equipment, Tooling, Material types) rather than one per resource - see `App\Support\CsvImport\`. `ResourceImporter` is the abstract shape each resource implements: its template columns, how a raw row resolves into model attributes (including name-based relational lookups - `material_type` on Materials, a `;`-separated `cuts` list on Tooling - resolved against the shop's own records, never auto-created), and the validation those attributes must pass. `ImporterRegistry` maps a route's `{resource}` key to one. Owner-only (`can:manage-shop`), gated per-resource by the same `Feature` each resource's own pages already require.

Processing is queued (`ProcessCsvImport`) - the upload endpoint returns the new `Import` row's id as plain JSON (not an Inertia redirect, since `CsvImportDialog.tsx` stays open on the current page rather than navigating), and the dialog polls `GET /imports/status/{import}` (also plain JSON) until status leaves `pending`. A row is matched to an existing record by case-insensitive `name` - matched, checkbox off, it's skipped; checkbox on, its fields are replaced. That dedupe check also looks at what the *same import run* has already created/updated (not just the database), so two rows in one file with the same name, however they differ in case, resolve as one record. A header missing a required column fails the whole file before any row is touched; one bad row among valid ones is reported by row number while the rest still import.

Console/queue context has no authenticated user, so - like the demo commands and `NotifyExpiredTrials` - every query inside `ProcessCsvImport` filters by shop_id explicitly; the tenant global scope is a no-op there. Watch for the same silent-mass-assignment trap `Invitation::accepted_at` hit: `Import`'s count/error columns must be in its `$fillable` list, or `$import->update([...])` on them does nothing and the row's `status` field is the only thing that visibly changes.

## Frontend formatting

`.prettierrc` matches the repo's style (single quotes, 4 spaces). Much of the existing code was hand-formatted rather than Prettier output, so format only files you create or substantially rewrite - running Prettier over the whole tree reflows lines in files you never touched.

## Shop settings and timezones

Per-shop settings (`shops.timezone`, `stale_quote_days`, `win_rate_window_days`) are nullable; null means "use `config('shop.defaults.*')`", read through `Shop::localTimezone()` / `staleQuoteDays()` / `winRateWindowDays()` - never the raw column.

Everything is stored in UTC. Anything that depends on the shop's calendar (month boundaries, "today", dates shown to people, invoice dates) goes through `Shop::localNow()` / `localTimezone()`; the frontend formats timestamps with `useShopDateTime()` (`resources/js/lib/datetime.ts`), which uses the shared `shop.timezone` rather than the viewer's browser. The trial window is the deliberate exception: `trial_started_at`/`trial_ends_at` are fixed UTC instants set at signup (`Shop::TRIAL_DAYS` x 24h), never derived from the timezone, so changing timezone can't lengthen a trial. Two traps, both hit while building this:
- Call `->utc()` before assigning a shop-local Carbon to a model attribute - Eloquent stores the wall-clock digits and drops the offset.
- Call `->copy()` before `->setTimezone()` on a model's date attribute - Carbon dates are mutable, so converting in place changes the attribute and a later `save()` writes the local time as if it were UTC.

## Marketing page (F8a)

`/` (`MarketingController`, page component `Welcome.tsx`) is the public marketing/pricing page — not the authenticated `/dashboard`. Tier contents are read live from `Plan`/`Feature`/`plan_feature` (seeded by `PlanSeeder`), never hardcoded copy, so what a tier claims to include can't drift from what `Shop::hasFeature()` actually enforces. `Feature::isShipped()` marks F5–F7 (`material-tracking`, `weld-records`, `mobile-api`) as "Coming soon" on the page regardless of which plan grants them, since nothing in the app implements them yet — flip it to `true` per feature the day each one ships, or the page will start silently overclaiming. The Free plan is rendered specially ("every feature, free for 14 days") rather than as a checklist of all-false rows, since its real access comes from `Shop::onTrial()`, not from any features attached to the `free` Plan row itself.

F8b and F8c (below) are now built too - F8 as a whole is complete.

## Signup and onboarding (F8b/F8c)

Registration (`RegisteredUserController`) creates only the `User` - no `Shop` row. `User implements MustVerifyEmail` (the base `Authenticatable` class already provides the trait's `hasVerifiedEmail()`/`markEmailAsVerified()` unconditionally; the interface is the only thing the framework's `verified` middleware and `EmailVerificationPromptController` actually gate on) and `AppServiceProvider` wires `Registered -> SendEmailVerificationNotification` (not registered by default outside Fortify/Jetstream). A verified user with no shop, or whose shop hasn't finished onboarding, is redirected by the `onboarded` middleware (`EnsureShopIsOnboarded`, alias only - not global) to `/onboarding`; it's applied to `/dashboard` and the big authenticated route group in `routes/web.php`, deliberately never to the onboarding routes themselves (`auth`+`verified` only) or to `/`, `/invitations/*`, `/login`, `/register`.

`shops.onboarded_at` (null while mid-wizard) + `shops.onboarding_step` (`'team'` | `'catalog'` | null) drive `OnboardingController::show()`, which renders whichever step comes next - each step's own POST persists immediately (`storeShop`/`storeTeam`/`storeCatalog`), so refreshing mid-wizard resumes rather than loses progress. `ShopFactory` and the demo seed commands default to `onboarded_at => now()` (already-set-up shops) - only real signups start with it null. Steps 2 and 3 are skippable; a blank row (email or name) in either is dropped server-side before validation rather than rejected, since `useForm`'s `setData()` is async and can't be filtered-then-posted in the same handler (see `TeamStep.tsx`'s comment) - `router.post()` with an explicit literal payload is used for "Skip", not `useForm`'s own `post()`.

Team invites are `App\Models\Invitation` (`shop_id`, `email`, `role`, `token`, `expires_at`, `accepted_at`) - deliberately **not** `BelongsToShop`-scoped, since whoever looks one up by token isn't a member of that shop yet (and may be signed into a *different* one already); the token itself is the access boundary. `App\Actions\SendShopInvitation` creates/refreshes the row (`updateOrCreate` on `[shop_id, email]`, so re-inviting refreshes one row) and sends `ShopInvitationSent` via an on-demand route (`Notification::route('mail', $email)->notify(...)`), not `->notify()` on a `User`, since the invitee usually has no account yet. Used by both onboarding's team step and Team page's own "Invite by email" (owner-only via `manage-shop`, not the delegable `team.manage-access` - inviting grants shop access, not just an editable setting).

`InvitationController` (public, no `auth`/`guest` middleware - every auth state is handled explicitly) resolves one of five modes: `accepted`, `expired`, `ready` (already signed in as the invitee), `login-required` (an account exists, needs a password), `create-account` (no account yet). Accepting sets the joining user's `current_shop_id` to the invited shop and assigns the invited role there via Spatie's team context - never touches any other shop they belong to. A newly-created account is marked `email_verified_at` immediately (clicking the mailed link is itself proof of ownership) via direct assignment, not mass assignment - `email_verified_at` isn't in `User`'s `#[Fillable(...)]` list, same reasoning as `current_shop_id`.

## Platform admin console (F10)

Internal, cross-tenant staff tooling - entirely separate from the per-shop Owner/Admin/Staff roles. Gated by `users.is_platform_admin` (not fillable, not settable from any form - grant it via `php artisan app:grant-platform-admin {email}` or `forceFill` in tinker only) via a dedicated `platform-admin` Gate, checked with `can:platform-admin` route middleware. `PlatformAdminController`'s routes (`/platform-admin/*`) deliberately skip the `active`/`onboarded` middleware the rest of the authenticated app requires - platform staff aren't acting within a shop here, and may not belong to one at all. `PlatformAdminLayout.tsx` is a separate, minimal layout for this reason (`AuthenticatedLayout`'s nav assumes a current shop throughout).

The shop directory reads every `Shop` with no tenant filtering - the one legitimate cross-tenant read in the app, since `Shop` is the tenant record itself, not a `BelongsToShop`-scoped one. Two moderation actions: suspend (`shops.suspended_at`, nullable, not fillable - set only via `forceFill` in the controller, same pattern as `onboarded_at`) and an admin-initiated plan change that bypasses the shop's own Owner-only plan-pick UI. A suspended shop's members hit a new `EnsureShopIsActive` middleware (alias `active`, applied alongside `onboarded`) that redirects them to `/shop-suspended` - a plain notice, not a 403, since suspension is a moderation state to explain rather than a request they got wrong.

Potential revenue (active MRR vs. trial-potential MRR, both computed from `plans.monthly_price_cents` x shop counts, excluding suspended shops) is explicitly **not** real collected revenue - no Stripe/Square/Plaid integration exists. `monthly_price_cents` didn't exist on `Plan` before this and is seeded with placeholder numbers (Free $0, Start-up $49, Pro $99) pending an actual pricing decision. Also still open: no audit logging of what a platform admin viewed or changed, and no written internal-access policy - both flagged as needed before this touches real shop data, not just implementation follow-ups. See `docs/product-desc.md`'s F10 section for the full design note.

## Current stage

Greenfield build. Feature build order: Foundation → Customers → (Subscription billing / Feature gating, in parallel) → Quote builder → Quote-to-job conversion → Job/traveler board → QuickBooks sync → (Material tracking / Mobile+API / Weld records, in parallel).

See [docs/product-desc.md](docs/product-desc.md) for the full feature dependency table, package list, subscription tier/feature-gating design, and user-flow diagrams — read it before starting work on a new feature area.
