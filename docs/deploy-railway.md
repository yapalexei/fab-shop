# Deploying to Railway

The whole project - Postgres, Redis, a bucket, and the `web` / `horizon` / `scheduler` / `reverb` services - is declared in [.railway/railway.ts](../.railway/railway.ts) (Railway Infrastructure as Code, beta). All four app services build from the root `Dockerfile` and differ only in start command. `web` runs `migrate --force` and `db:seed --class=PlanSeeder --force` as its single pre-deploy command (Railway allows only one, so they're chained; the seeder is idempotent; plans/features are required data, not demo data).

Two separate mechanisms, on purpose:

- **App code** - each service is connected to `main` on GitHub, so `git push origin main` builds and deploys all four.
- **Infrastructure** (`.railway/**`) - [.github/workflows/railway.yml](../.github/workflows/railway.yml) runs `railway config plan` on a PR and `railway config apply` when it merges. Needs a `RAILWAY_TOKEN` repo secret (an environment-scoped project token from Railway's project settings → Tokens).

## One-time bootstrap

1. Upgrade the CLI - IaC needs **5.42.1+** (`brew upgrade railway` or `npm i -g @railway/cli`).
2. `railway login`, create an empty project in the dashboard (or `railway init`), then `railway link` in this repo. Install the Railway GitHub app on the repo.
3. `railway config plan` to preview, then `railway config apply`. This creates every service and database and wires the variables between them.
4. Generate the two public domains: `railway domain --service web` and `railway domain --service reverb`.
5. Set the values IaC deliberately doesn't own (secrets aren't declared in `railway.ts` at all, so apply never touches them) on each service that needs them - see "Variables you set by hand".
6. Redeploy. Reverb's domain is baked into the JS bundle at build time, so `web` must build *after* step 4.
7. Add the `RAILWAY_TOKEN` GitHub secret so later infrastructure edits apply from CI.

## Why a bucket is required

Services don't share a filesystem. Job photos, generated quote PDFs and CSV import uploads are written by one container and read by another, so they must live on shared storage. `FILESYSTEM_DISK=s3` + `MEDIA_DISK=s3` do that; photo URLs are signed temporary URLs, so the bucket can stay private.

## Variables you set by hand

Everything else in the old variable list (database/Redis/bucket references, reverb hosts, drivers) now comes from `railway.ts`. Set these once per service that uses them (`railway variables --service web --set KEY=value`, or the dashboard):

```
APP_KEY=base64:...            # php artisan key:generate --show
APP_URL=https://<web domain>
REVERB_APP_ID / REVERB_APP_KEY / REVERB_APP_SECRET   # any values; identical on every service
VITE_REVERB_APP_KEY=<same as REVERB_APP_KEY>         # web, horizon, scheduler (build-time)
RESEND_API_KEY                # web + horizon (mail is sent from queued jobs too)
MAIL_FROM_ADDRESS             # must be on a domain verified in Resend
LEGAL_*                       # see .env.example
QUACKBACK_URL / QUACKBACK_SIGNING_SECRET   # web only; feedback widget (docs/context/feedback-widget.md). Secret is server-only; leave both unset to run without the widget
```

QuickBooks variables stay unset - the feature is switched off.

## After the first deploy

- Grant yourself platform admin (also gates `/horizon` outside local): Railway CLI/shell on `web` → `php artisan app:grant-platform-admin you@example.com`.
- Never set `APP_MODE=demo` in production (the `demo:*` commands refuse to run without it).
- Horizon needs the `web` and `horizon` services to share the same `REDIS_URL` and `APP_NAME`/`HORIZON_PREFIX`.

## Verified / not verified

Applied successfully to a real project: all four services deploy, `/up` returns 200, migrations and the plan seeder run, Horizon and the scheduler start, Reverb listens, and the `VITE_REVERB_*` values end up in the built JS bundle.

Gotchas hit on the first deploy:
- `preDeployCommand` accepts a single command only.
- `web`'s bundle must build *after* the `VITE_REVERB_*` variables exist - changing them needs a rebuild (set any variable without `--skip-deploys`).
- On a brand-new database `reverb` can start before `web` has migrated and crash on the missing `cache` table; redeploy `reverb` once.

Not yet exercised: the bucket (photo/PDF/CSV round-trip on S3 - the `AWS_*` reference names came from a guess), outgoing mail, and a live WebSocket connection through Railway's proxy.
