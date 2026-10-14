# fab-shop

Vertical SaaS for quoting, job-tracking, and invoicing for small custom/ornamental/mobile metal fabrication and welding shops (1-8 people) — replacing the QuickBooks-plus-spreadsheets-plus-paper-traveler status quo.

## How this file works

This file is deliberately short: the always-loaded rules and an index. Detail lives in topic files under [docs/context/](docs/context/), which are **not** loaded automatically. Before you change or answer questions about an area, find it in the [topic index](#topic-index) below and **Read the file(s) listed** — they hold the traps, invariants and "why" that the code alone doesn't show. If a task spans areas, read each. When you change behaviour a topic file describes, update that file in the same change. A new topic gets its own file in `docs/context/` plus a row in the index (don't grow this file).

## Directive: use the right AI model for the job

When delegating work to a subagent (the Agent tool's `model` parameter), pick the model by what the task needs, not by habit. Do small, tightly-coupled work yourself inline — delegate when the task is independent, bulky, or benefits from a different strength. Don't spend a bigger model on a mechanical task or a smaller one on a judgment-heavy one.

| Model | Best at | Use it for here |
|---|---|---|
| **Haiku** (`haiku`) | Speed and low cost on well-defined, mechanical work | Codebase searches and "where is X used" sweeps, listing/grepping across files, bulk find-and-replace style edits, summarising logs or long files, formatting/lint cleanups, drafting boilerplate (factories, simple migrations) from an existing sibling |
| **Sonnet** (`sonnet`) | Balanced everyday engineering — the default | Implementing features and controllers/pages that follow existing patterns, writing and fixing Pest tests, routine refactors, UI components, CSV importers, docs updates |
| **Opus** (`opus`) | Deep reasoning, careful multi-step analysis | Tenancy/permission design, security and prompt-injection review (see `ai-prompt-injection`), tricky debugging across layers, concurrency/queue/timezone bugs, architecture trade-offs, reviewing a large diff for correctness |
| **Fable** (`fable`) | The most capable tier — hardest, highest-stakes, long-horizon work | Reserve for problems the others got wrong or that are expensive to get wrong: a gnarly cross-cutting redesign, final review of changes touching auth/billing/tenant isolation, ambiguous problems needing strong judgment |

Rules of thumb: search/read first with Haiku, build with Sonnet, escalate to Opus or Fable when the answer has security, money, or tenant-isolation consequences or a cheaper model has already failed once. Whoever writes the code is not the only reviewer for those areas — have a stronger model review it.

**The product's own AI is separate from this.** Features inside the app (quote drafting, follow-ups, catalog intake, the MCP server) call the shop's *own* bring-your-own-key provider through `App\Services\Ai\AiManager` — never call a model provider directly from app code, and never hard-code a model: models come from the curated list in `config/ai.php`. Read [ai-assistant](docs/context/ai-assistant.md) before touching any of it.

## Tech stack

- Laravel 13 (PHP 8.3+), Inertia.js + React + TypeScript, Tailwind CSS
- PostgreSQL, single database, shared schema
- Pest for testing, Pint for code style

## Always-on rules

- **Run everything through Sail.** The app runs in Docker; bare host `php artisan` / `npm` can't reach `pgsql`/`mailpit`. Use `./vendor/bin/sail artisan ...`, `./vendor/bin/sail test`, etc.; bring the stack up with `./vendor/bin/sail up -d`. Tests need the `pgsql` container (real Postgres `testing` DB, not sqlite). Exception: shadcn/`npx` commands run on the host (macOS native binaries in `node_modules`). Details: [dev-environment](docs/context/dev-environment.md).
- **Multi-tenancy is critical.** Every tenant-owned table has `shop_id` and uses the `BelongsToShop` trait + global scope, keyed off `users.current_shop_id`. **Never** add `->where('shop_id', ...)` at a call site. Every new tenant-owned model needs a cross-tenant-isolation test (Shop A user can't read/write Shop B). Queued/console code has no auth user, so it must filter by `shop_id` explicitly. Details: [tenancy-and-permissions](docs/context/tenancy-and-permissions.md).
- **Permissions are `{resource}.{action}`** (`App\Enums\ShopResource`, `App\Support\ShopPermissions`), each also a Gate ability. Gate financial data **on the server** (omit the Inertia prop), not just in the UI. New permission = enum action + `defaultsFor()` + a migration calling `ShopPermissions::seedRoles()`.
- **Two editions, one codebase.** Plans/trials/billing/platform-admin/our legal pages are cloud-only: gate routes with the `cloud` middleware, scheduled commands with `->when(Edition::cloud())`, and entitlement only through `Shop::hasFeature()` so `APP_MODE=selfhosted` keeps working. See [self-hosted](docs/context/self-hosted.md).
- **Gate features, don't hardcode plans.** Plans are versioned rows a shop is pinned to: list/assign via `Plan::orderedByTier()` / `Plan::offeredByKey()`, never `where('key', ...)` for a new assignment. Entitlement goes through `Shop::hasFeature()` / `Feature`; what the marketing page claims is read from `Plan`/`Feature`, and `Feature::isShipped()` controls "Coming soon". See [plans-and-pricing](docs/context/plans-and-pricing.md) and [marketing-and-onboarding](docs/context/marketing-and-onboarding.md).
- **Time:** store UTC; anything calendar-dependent goes through `Shop::localNow()`/`localTimezone()`. Read per-shop settings via their `Shop` accessors, never the raw column. See [shop-settings-timezones](docs/context/shop-settings-timezones.md).
- **Files:** services don't share a filesystem in production — use the default/media disk, never `Storage::disk('local')->path()` or `Media::getPath()`. See [deployment](docs/context/deployment.md) and [media-and-documents](docs/context/media-and-documents.md).
- **New access-changing action = add a `SecurityLog::record()` call.** New inline `<script>` needs the CSP nonce. See [security-controls](docs/context/security-controls.md).
- **Mass-assignment trap:** columns set by `update([...])` must be in `$fillable`, and flags that must not be user-settable (`suspended_at`, `onboarded_at`, `is_agent`, `ai_touched_at`...) are deliberately *not* — set them with `forceFill`/dedicated methods.
- **Frontend:** use semantic Tailwind tokens (`bg-background`, `text-muted-foreground`), not gray/white; format only files you create or substantially rewrite; don't Prettier `resources/js/Components/ui/`. See [frontend](docs/context/frontend.md).
- Pest for tests, Pint for PHP style. The Laravel Boost guidelines in `AGENTS.md` and the skills in `.claude/skills/` also apply.

## Topic index

Read the file when your task touches the area. "Read when" is the trigger.

| Topic file | Read when |
|---|---|
| [dev-environment](docs/context/dev-environment.md) | Starting/stopping the stack, Horizon/scheduler/Reverb problems, a 500 on a status change, `storage:link`, demo data (`demo:seed-team`, `demo:seed-multi-shop`, `APP_MODE=demo`) |
| [deployment](docs/context/deployment.md) | Railway, Dockerfile(s), env vars, the `ssr` service, S3/disk behaviour, anything that differs between Sail and production |
| [tenancy-and-permissions](docs/context/tenancy-and-permissions.md) | Any new model/table, scoping bugs, shop switching, roles, `can:` middleware, per-member overrides, markup visibility, delete guards |
| [security-controls](docs/context/security-controls.md) | Login, two-factor policy, security/audit log, sessions, CSP and headers, shop data export/erasure, security pages (also [docs/security.md](docs/security.md) runbooks) |
| [shop-settings-timezones](docs/context/shop-settings-timezones.md) | Anything date/time or per-shop setting (timezone, stale-quote days, win-rate window), trial dates |
| [media-and-documents](docs/context/media-and-documents.md) | Avatars, logos, material photos, certification documents, image serving |
| [catalog-and-tooling](docs/context/catalog-and-tooling.md) | Material types, tools and what they cut, catalog materials |
| [job-board](docs/context/job-board.md) | Job stages, moving/cancelling jobs, drag-and-drop, ready-to-invoice, `Job::canTransitionTo` |
| [material-tracking](docs/context/material-tracking.md) | F5: actual material use/scrap ledger, job cost variance |
| [qualification-records](docs/context/qualification-records.md) | F7: procedures, certifications, job qualification records, `was_qualified` snapshot |
| [csv-import](docs/context/csv-import.md) | F9: importers, column mapping, supplier price lists, `ProcessCsvImport` |
| [quickbooks](docs/context/quickbooks.md) | F4: QuickBooks sync (currently switched off) and re-enabling it |
| [marketing-and-onboarding](docs/context/marketing-and-onboarding.md) | F8: the public `/` page, registration, email verification, onboarding wizard, team invitations |
| [plans-and-pricing](docs/context/plans-and-pricing.md) | Plan tiers, prices, seats and extra seats, trial length, what each tier includes; **editing/adding/retiring tiers, tier versions & history, expiry dates, what existing shops get when a tier's price/features change (immediate drops, announced increases with an effective date, carried-over features, the before/after notice to owners, discontinuing a feature), the landing-page comparison table and the upgrade-page cost calculator, anything that looks a plan up by key or lists plans** |
| [self-hosted](docs/context/self-hosted.md) | `APP_MODE=selfhosted`: the single-shop edition (`Edition`, the `cloud` middleware, `/setup`, `selfhosted:install`), anything plan/trial/billing/platform-admin/legal-page related that must stay out of it, the Docker bundle (`docker/selfhosted/`: Caddy, same-origin Reverb, install/backup/restore), `.dockerignore`, known gaps |
| [platform-admin](docs/context/platform-admin.md) | F10: internal cross-tenant console, suspending shops, plan overrides, potential revenue |
| [legal-and-consent](docs/context/legal-and-consent.md) | Terms/privacy/SMS pages, consent records, checkbox wording and versions, agreeing to the Terms when subscribing, the price-change clause |
| [seo](docs/context/seo.md) | `robots.txt`, `sitemap.xml`, adding a public page, `->sitemap()` / `->noindex()`, **server-side rendering (Inertia SSR, hydration), meta/OG/JSON-LD tags, `PageMeta`** |
| [frontend](docs/context/frontend.md) | shadcn/Tailwind v4 theming, UI components, Prettier policy |
| [ai-assistant](docs/context/ai-assistant.md) | BYOK AI settings, `AiManager`, quote intake, follow-up drafts, supplier-page intake, `ai_requests` |
| [ai-mcp-server](docs/context/ai-mcp-server.md) | The `/mcp` endpoint, agent users, MCP tools and permission ceiling (`AgentAccess`), tokens |
| [ai-prompt-injection](docs/context/ai-prompt-injection.md) | **Anything an AI can write or read**: the lethal-trifecta defences, review flags, `UntrustedText`, read-only tokens. Read before loosening or adding any AI write path |

Other references: [docs/product-desc.md](docs/product-desc.md) — full feature dependency table, package list, subscription/feature-gating design and user flows (read before starting a new feature area); [docs/security.md](docs/security.md) — security controls and runbooks; [docs/deploy-railway.md](docs/deploy-railway.md) — full Railway setup and variable list; [docs/ai-sample-rfqs.md](docs/ai-sample-rfqs.md) — sample RFQs for exercising AI drafting.

## Current stage

Greenfield build. Feature build order: Foundation → Customers → (Subscription billing / Feature gating, in parallel) → Quote builder → Quote-to-job conversion → Job/traveler board → QuickBooks sync → (Material tracking / Mobile+API / Qualification records, in parallel). Mobile+API (F6) is the only one of that last group not yet built.
