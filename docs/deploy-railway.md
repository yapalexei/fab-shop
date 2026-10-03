# Deploying to Railway

One `Dockerfile` builds one image; Railway runs it as four app services plus two databases. Each app service points at its own config file (Service → Settings → **Config-as-code → Config file path**):

| Service     | Config file              | Runs                      | Public domain |
|-------------|--------------------------|---------------------------|---------------|
| `web`       | `/railway/web.json`      | nginx + php-fpm (default) | yes           |
| `horizon`   | `/railway/horizon.json`  | `artisan horizon`         | no            |
| `scheduler` | `/railway/scheduler.json`| `artisan schedule:work`   | no            |
| `reverb`    | `/railway/reverb.json`   | `artisan reverb:start`    | yes (WebSockets) |

Plus **PostgreSQL** and **Redis** (Railway database templates) and a **Bucket** (S3-compatible storage).

`web` runs `migrate --force` and `db:seed --class=PlanSeeder --force` as its pre-deploy command (the seeder is idempotent; plans/features are required data, not demo data).

## Why a bucket is required

Services don't share a filesystem. Job photos, generated quote PDFs and CSV import uploads are written by one container and read by another, so they must live on shared storage. `FILESYSTEM_DISK=s3` + `MEDIA_DISK=s3` do that; photo URLs are signed temporary URLs, so the bucket can stay private.

## Setup

1. New project → deploy from this GitHub repo (creates `web`). Add `horizon`, `scheduler`, `reverb` as three more services from the same repo. Set each one's config file path.
2. Add Postgres, Redis, and a Bucket.
3. Put the shared variables in **Project → Shared Variables** (or reference them per service with `${{shared.NAME}}`), and give every app service the same values. Variables below marked *build* must also be present on the service at build time.
4. Generate a public domain for `web` and for `reverb`.
5. Deploy `reverb` first (its domain is needed for the `VITE_*` build args), then the rest.

## Variables (all four app services)

```
APP_NAME="Fab Shop"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                  # php artisan key:generate --show
APP_URL=https://<web domain>
LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_URL=${{Postgres.DATABASE_URL}}
REDIS_URL=${{Redis.REDIS_URL}}
QUEUE_CONNECTION=redis
CACHE_STORE=database
SESSION_DRIVER=database

FILESYSTEM_DISK=s3
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=${{Bucket.ACCESS_KEY_ID}}
AWS_SECRET_ACCESS_KEY=${{Bucket.SECRET_ACCESS_KEY}}
AWS_DEFAULT_REGION=${{Bucket.REGION}}
AWS_BUCKET=${{Bucket.BUCKET}}
AWS_ENDPOINT=${{Bucket.ENDPOINT}}
AWS_USE_PATH_STYLE_ENDPOINT=true    # check the bucket's own variable names in the Railway UI

MAIL_MAILER=smtp                    # or resend/postmark - mailpit does not exist here
MAIL_HOST=...  MAIL_PORT=...  MAIL_USERNAME=...  MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<any number>
REVERB_APP_KEY=<random string>
REVERB_APP_SECRET=<random string>
REVERB_HOST=<reverb public domain>  # what the browser connects to
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_INTERNAL_HOST=${{reverb.RAILWAY_PRIVATE_DOMAIN}}   # server-to-server push
REVERB_INTERNAL_PORT=8080
REVERB_INTERNAL_SCHEME=http

# build args (baked into the JS bundle)
VITE_REVERB_APP_KEY=${{REVERB_APP_KEY}}
VITE_REVERB_HOST=${{REVERB_HOST}}
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

LEGAL_*=...                         # see .env.example
```

Extra per service:

- `web`: `PORT=8080`, `PHP_OPCACHE_ENABLE=1`
- `reverb`: `PORT=8080`, `REVERB_SERVER_PORT=8080` (it listens on 8080; Railway's edge proxies 443 to it)
- QuickBooks vars: leave unset - the feature is switched off.

## After the first deploy

- Grant yourself platform admin (also gates `/horizon` outside local): Railway CLI/shell on `web` → `php artisan app:grant-platform-admin you@example.com`.
- Never set `APP_MODE=demo` in production (the `demo:*` commands refuse to run without it).
- Horizon needs the `web` and `horizon` services to share the same `REDIS_URL` and `APP_NAME`/`HORIZON_PREFIX`.

## Not verified

The image builds and boots locally (extensions, artisan commands, asset build). The Railway wiring itself - reference-variable names for the Bucket, the Reverb WebSocket round-trip through Railway's proxy - hasn't been exercised against a live project, so check the first deploy's logs for each service.
