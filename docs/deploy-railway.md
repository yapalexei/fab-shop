# Deploying to Railway

The whole project - Postgres, Redis, a bucket, and the `web` / `horizon` / `scheduler` / `reverb` / `ssr` services - is declared in [.railway/railway.ts](../.railway/railway.ts) (Railway Infrastructure as Code, beta). The four PHP services build from the root `Dockerfile` and differ only in start command; `ssr` is a plain Node service built from [Dockerfile.ssr](../Dockerfile.ssr) (see [The SSR service](#the-ssr-service-public-pages-for-search-engines)). `web` runs `migrate --force` and `db:seed --class=PlanSeeder --force` as its single pre-deploy command (Railway allows only one, so they're chained; the seeder is idempotent; plans/features are required data, not demo data).

Two separate mechanisms, on purpose:

- **App code** - each service is connected to `main` on GitHub, so `git push origin main` builds and deploys all five.
- **Infrastructure** (`.railway/**`) - [.github/workflows/railway.yml](../.github/workflows/railway.yml) runs `railway config plan` on a PR and `railway config apply` when it merges. Needs a `RAILWAY_TOKEN` repo secret (an environment-scoped project token from Railway's project settings → Tokens).

## One-time bootstrap

1. Upgrade the CLI - IaC needs **5.42.1+** (`brew upgrade railway` or `npm i -g @railway/cli`).
2. `railway login`, create an empty project in the dashboard (or `railway init`), then `railway link` in this repo. Install the Railway GitHub app on the repo.
3. `railway config plan` to preview, then `railway config apply`. This creates every service and database and wires the variables between them.
4. Generate the two public domains: `railway domain --service web` and `railway domain --service reverb`. (Not `ssr` - it stays private.)
5. Set the values IaC deliberately doesn't own (secrets aren't declared in `railway.ts` at all, so apply never touches them) on each service that needs them - see "Variables you set by hand".
6. Redeploy. Reverb's domain is baked into the JS bundle at build time, so `web` must build *after* step 4.
7. Add the `RAILWAY_TOKEN` GitHub secret so later infrastructure edits apply from CI.

## The SSR service (public pages for search engines)

The marketing page (`/`) and the legal pages are server-rendered so crawlers get real HTML instead of an empty shell that only fills in after JavaScript runs. Laravel can't render React itself, so the `ssr` service - a small Node process - does it: for each first load of a public page, `web` POSTs the page's props to `ssr`, which answers with the rendered HTML, and `web` puts that in the response.

- **Private by design.** `web` reaches it over Railway's private network (`INERTIA_SSR_HOST` = the `ssr` service's `RAILWAY_PRIVATE_DOMAIN`, `INERTIA_SSR_PORT` = `13714`; `config/inertia.php` builds the URL). Never give it a public domain: its `/shutdown` endpoint is unauthenticated.
- **Optional at runtime.** If `ssr` is down, slow (3s timeout) or not deployed yet, `web` serves the same pages client-rendered - the site stays up, only the SEO benefit pauses. It logs `Inertia SSR unavailable` (at most once a minute) so you notice.
- **Only the public pages.** The routes marked `->sitemap()` are rendered; everything behind a login never touches it ([seo](context/seo.md)).
- **Built from the same commit as `web`.** It rebuilds on every push to `main`, so the server-rendered HTML and the browser bundle that hydrates it never come from different versions of a page. `VITE_APP_NAME` must be the same on both (it's the `<title>` suffix).

### Create it - with Infrastructure as Code

`ssr` and the two `web` variables above are already declared in `railway.ts`. Run `railway config plan`, check the diff, then `railway config apply` (or merge the PR - CI applies it).

> **Read the whole plan first.** `apply` deletes everything in the live environment that `railway.ts` doesn't declare, and `plan` lists those as `- Delete ...`; CI applies on merge. The project also hosts Quackback (the feedback board - its own app, pgvector Postgres, Dragonfly and bucket, in a `Quackback` group), so those are declared in `railway.ts` too, with every variable kept via `preserve()`. Anything else you add to the project by hand needs the same treatment (`railway config pull --json` shows the live state; plain `pull` rewrites `railway.ts`, so run it only on a clean tree). A plan with unexpected `- Delete` lines should stop you.

### Create it - by hand with the CLI (does not touch anything else)

Same result without `apply`, from this repo (already `railway link`ed):

```bash
# 1. The renderer. RAILWAY_DOCKERFILE_PATH makes Railway build Dockerfile.ssr instead of Dockerfile.
railway add --service ssr --repo yapalexei/fab-shop --branch main \
  --variables "RAILWAY_DOCKERFILE_PATH=Dockerfile.ssr" \
  --variables "PORT=13714" \
  --variables "VITE_APP_NAME=Fab Shop"

# 2. Tell web where it is (single quotes: Railway, not your shell, resolves ${{...}}).
railway variable set --service web \
  'INERTIA_SSR_HOST=${{ssr.RAILWAY_PRIVATE_DOMAIN}}' \
  INERTIA_SSR_PORT=13714
```

Then in the dashboard, `ssr` → Settings → Deploy: set the healthcheck path to `/health`. Setting the `web` variables redeploys it, which is what you want.

Don't generate a domain for `ssr`. `railway.ts` already declares it, so a later apply will take it over and set the same Dockerfile and variables, so nothing here needs undoing.

### Check it works

```bash
# Rendered on the server: the headline is in the raw HTML, not just after JavaScript runs.
curl -s https://<web domain>/ | grep -o '<h1[^>]*>[^<]*'
# One <title>, a description and a canonical link, and JSON-LD pricing:
curl -s https://<web domain>/ | grep -o -E '<title[^>]*>[^<]*|name="description"|rel="canonical"|application/ld\+json'
railway service logs --service ssr   # "Inertia SSR server started."
railway service logs --service web | grep "SSR unavailable"   # should be empty
```

If the `<h1>` isn't in the `curl` output, `web` isn't reaching `ssr`: check `INERTIA_SSR_HOST` on `web`, that `ssr` is deployed and healthy, and that `bootstrap/ssr/ssr.js` is in the `web` image (the root `Dockerfile` copies it). Google's URL Inspection tool ("View crawled page") shows what Googlebot got.

### Locally (optional)

Sail doesn't run the renderer by default - without a built bundle, `web` skips SSR and pages are client-rendered as before. To check server rendering locally: `npm run build:ssr` on the host (same `node_modules` as `npm run build`), then `./vendor/bin/sail artisan inertia:start-ssr` and load `/` signed out. Rebuild after changing a public page - the renderer serves the bundle it started with, not your hot-reloading code. A stale `bootstrap/ssr` with no renderer running only costs one refused connection per page and a throttled log line.

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
```

QuickBooks variables stay unset - the feature is switched off.

## After the first deploy

- Grant yourself platform admin (also gates `/horizon` outside local): Railway CLI/shell on `web` → `php artisan app:grant-platform-admin you@example.com`.
- Never set `APP_MODE=demo` in production (the `demo:*` commands refuse to run without it).
- Horizon needs the `web` and `horizon` services to share the same `REDIS_URL` and `APP_NAME`/`HORIZON_PREFIX`.

## Verified / not verified

Applied successfully to a real project (before `ssr` existed): all four PHP services deploy, `/up` returns 200, migrations and the plan seeder run, Horizon and the scheduler start, Reverb listens, and the `VITE_REVERB_*` values end up in the built JS bundle.

Gotchas hit on the first deploy:
- `preDeployCommand` accepts a single command only.
- `web`'s bundle must build *after* the `VITE_REVERB_*` variables exist - changing them needs a rebuild (set any variable without `--skip-deploys`).
- On a brand-new database `reverb` can start before `web` has migrated and crash on the missing `cache` table; redeploy `reverb` once.

`ssr` is exercised locally - the Node bundle runs standalone (no `node_modules`), Laravel renders through it, the page hydrates in Chromium with no mismatch errors, and with the renderer off the page falls back cleanly - but it hasn't been deployed to Railway yet, so the private-network hop (`INERTIA_SSR_HOST` over IPv6) and its `/health` check are unverified there.

Not yet exercised: the bucket (photo/PDF/CSV round-trip on S3 - the `AWS_*` reference names came from a guess), outgoing mail, and a live WebSocket connection through Railway's proxy.
