# Local development (Sail)

The app runs via Laravel Sail (Docker), not the host PHP — `.env`'s `DB_HOST=pgsql`/`MAIL_HOST=mailpit` only resolve inside Sail's Docker network, so bare `php artisan` / `npm` on the host will fail to connect. Use `./vendor/bin/sail artisan ...`, `./vendor/bin/sail npm ...`, `./vendor/bin/sail test`, etc. (alias `sail` to `./vendor/bin/sail` for less typing). Bring the stack up with `./vendor/bin/sail up -d`.

Services: `pgsql` (Postgres, matches production, default port 5432), `redis` (queue backend for Horizon), `mailpit` (catches all outgoing mail — inbox UI at `http://localhost:8025`), `horizon`, `scheduler`, and `reverb` (all below). App runs at `http://localhost` (port 80).

The test suite also runs against a real Postgres `testing` database (via `phpunit.xml`), not sqlite — so `./vendor/bin/sail test` needs the `pgsql` container up. Tests force `QUEUE_CONNECTION=sync` and `CACHE_STORE=array` (also in `phpunit.xml`), so they never touch Redis or Horizon.

**Queue worker:** the `horizon` service runs `artisan horizon` as its own container (overriding Sail's default entrypoint, same image) and starts automatically with `./vendor/bin/sail up -d` — quote PDF generation/email (F2) and any future queued work just processes on its own, no manual `queue:work` needed. Dashboard at `http://localhost/horizon` (gated by `HorizonServiceProvider::gate()` outside local env). `QUEUE_CONNECTION=redis` — Horizon only supports Redis.

**Scheduler:** the `scheduler` service runs `artisan schedule:work` the same way (own container, overridden entrypoint) so `routes/console.php`'s `Schedule::command(...)` entries (currently just `app:notify-expired-trials`, hourly - a trial ends 14 x 24h after signup, at any time of day) actually fire without a real cron. `schedule:work` polls once a minute and re-evaluates every registered schedule; check what's due with `./vendor/bin/sail artisan schedule:list`.

**Reverb** (job board live updates, F3) runs the same way — its own `reverb` container (entrypoint overridden to `artisan reverb:start`), port 8080. Job board broadcasts use `ShouldBroadcastNow` specifically so they don't *need* a queue worker, but Reverb itself still has to be running to accept the WebSocket connection, which it now is by default.

Splitting Reverb into its own container broke server-to-server broadcasting until it was caught and fixed: `REVERB_HOST=localhost` is right for the *browser* (it connects through the port Docker forwards to the host), but this app also has to push events to Reverb itself when something happens (`broadcast(...)`), and from inside the `laravel.test` container "localhost" no longer means anything - Reverb is a separate container now. Fixed with a second env var, `REVERB_INTERNAL_HOST=reverb` (the Docker service name), which `config/broadcasting.php`'s `reverb` connection uses for its `host` option instead of `REVERB_HOST`; the browser-facing `VITE_REVERB_HOST` is untouched. Because `JobBoardUpdated` is `ShouldBroadcastNow`, a broken connection here throws mid-request - the status *does* still save (the DB update happens before the broadcast call), but the request comes back as a 500. If that happens again, check `storage/logs/laravel.log` for a Pusher/cURL connection error before assuming the state machine itself is broken.

`./vendor/bin/sail artisan storage:link` is required once per environment for anything served off the `public` disk (job photos) — quote PDFs don't need it since they're streamed through a controller route instead.

**SSR** is off locally unless you build the bundle: no `bootstrap/ssr`, no server rendering, and the public pages render in the browser as always. To try it, `npm run build:ssr` on the host then `./vendor/bin/sail artisan inertia:start-ssr` (rebuild after editing a public page; `npm run dev` hot reload does not reach the renderer). Details: [seo](seo.md).

## Demo data

`demo:seed-team` and `demo:seed-multi-shop` build realistic, fully interconnected data for manual testing — a shop with a full team + catalog (incl. tooling - the same 1/4in bit for steel and for aluminum) + an in-progress job, plus six months of invoiced jobs and open/stale/lost quotes so the dashboard has real numbers (first command), and two isolated shops plus a user who belongs to both, to click through tenant isolation and the shop switcher (second command). Both print login credentials (password `password` for every seeded user) and accept `--fresh` to replace their own data on a rerun.

`APP_MODE=selfhosted` is a different thing - the single-shop edition, see [self-hosted](self-hosted.md); it is mutually exclusive with `demo`.

Both refuse to run unless `APP_MODE=demo` is set (on top of a non-production `APP_ENV`) — two independent gates, so a stray env misconfiguration can't run them somewhere real data lives. `APP_MODE` is separate from `APP_ENV` on purpose: local dev without `APP_MODE=demo` set still refuses to run these.
