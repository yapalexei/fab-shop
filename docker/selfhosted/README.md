# Running Fab Shop on your own server

This runs one shop's private copy of Fab Shop on a computer you control, using Docker. Your data stays on that computer.

## What you need

- A computer or server that stays on during working hours: a Linux box, a small cloud server, or a Mac/Windows PC running [Docker](https://docs.docker.com/get-docker/). 2 GB of memory is enough for a small shop.
- If people will use it away from the shop (phones on the road, a customer-facing link): a domain name pointing at the server, with ports 80 and 443 open.
- An email account (SMTP) so team invitations and password resets can be sent.

## Install

```bash
git clone <this repository> && cd fab-shop/docker/selfhosted
./install.sh
```

It asks for the address people will use, generates fresh secrets, builds and starts everything (the first build takes a few minutes) and prints a link. Open it to create the owner account and your shop.

| Address you give | What you get |
|---|---|
| `shop.example.com` | https with a free certificate, renewed automatically |
| `192.168.1.20` or `shop.local` | plain http, reachable on your network only |
| *(Enter)* | plain http on this computer only |

Plain http is fine inside your own building. Don't expose it to the internet - use a domain.

## Everyday

| | |
|---|---|
| Stop / start | `docker compose stop` / `docker compose start` (it also restarts itself after a reboot) |
| Logs | `docker compose logs -f web` |
| **Update** | `git pull && docker compose up -d --build` - database changes are applied on start |
| Backup | `./backup.sh` |
| Restore | `./restore.sh <stamp>` (the stamp is in the file names under `backups/`) |

## Email

Out of the box nothing is emailed: invitations and resets are written to the log (`docker compose logs web`) so you can copy a link by hand. To send real email, edit `.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="shop@yourdomain.com"
```

then `docker compose up -d`. Any SMTP service works.

## Backups - please read

`./backup.sh` writes the database, the uploaded files and a copy of `.env` into `backups/`. **That folder is on the same machine as the shop, so it does not protect you from losing the machine.** Copy it somewhere else regularly (an external drive, cloud storage). Run it from cron to make it automatic:

```
0 2 * * *  cd /path/to/fab-shop/docker/selfhosted && ./backup.sh
```

Keep `.env` safe: its `APP_KEY` is needed to read encrypted data (such as your AI key) in any backup. Restoring onto a new machine: install, copy your saved `.env` over the new one, then `./restore.sh <stamp>`.

## Known limits

- **Job photos are served from a public path** (`/storage/...`) rather than through a login check, as on a development install. The addresses are hard to guess but are not secret; don't put anything sensitive in a job photo. Quote attachments and certification documents are access-checked.
- No built-in monitoring or automatic updates: if the server is off, so is the shop.
- Updating pulls source and rebuilds on your machine; there is no prebuilt image yet.
- The Horizon queue dashboard (`/horizon`) is not available in this edition.

## How it fits together

`caddy` (the only thing exposed) → `web` (the app) and `reverb` (live job-board updates, reached at the same address under `/app`). `horizon` runs background jobs (emails, PDFs, imports), `scheduler` runs the periodic tasks. `pgsql` and `redis` are private to the stack. Data lives in Docker volumes: `pgdata`, `storage`, `redisdata`, and `caddy-data` (certificates).

For the design behind the self-hosted edition, see [docs/context/self-hosted.md](../../docs/context/self-hosted.md).
