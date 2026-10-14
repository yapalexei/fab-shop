# Feedback widget (Quackback)

A hosted feedback launcher ([Quackback](https://quackback.io), self-hosted instance) shown on every page. Two independent pieces of config, both in `config/services.php` (`quackback`):

- `QUACKBACK_URL` - the instance origin. **This alone turns the launcher on, for everyone, signed in or not.** Blank or not an http(s) URL = widget off everywhere (`App\Support\Quackback::url()` normalises it to `scheme://host[:port]`).
- `QUACKBACK_SIGNING_SECRET` - **server-only.** Signs the ssoToken that links a signed-in user to their feedback. Never a `VITE_*` variable, never in a prop, a log or a commit (`.env` is ignored; `.env.example` has the keys blank). It was issued by redeeming a one-time pairing code (`POST {instance}/api/widget/install-context`); a lost secret is re-copied from the instance's Admin > Settings > Widget > Install, never invented. Rotating it there means updating the env var here (and on Railway `web`).

## How the pieces fit

1. **Launcher**: `resources/views/app.blade.php` renders Quackback's loader snippet (nonce'd inline script that adds `INSTANCE/api/widget/sdk.js`, then `Quackback('init')`) only when `Quackback::url()` is set. Because it's in the root view it loads once per full page load, not per Inertia navigation.
2. **CSP**: `SecurityHeaders::policy()` adds the instance origin to `script-src`, `connect-src`, `frame-src` and `font-src` when configured - and only then. If the widget misbehaves in a browser, try `CSP_MODE=report-only` and read the console before widening anything.
3. **Identity** (we have login, so this is built): `GET /quackback/token` (`QuackbackTokenController`, route `quackback.token`) returns `{ ssoToken }` - an HS256 JWT with `sub` = the user's **id** (never email: it can change), `email`, `name`, `exp` = 5 minutes. It is built from the session only. It is deliberately **not** behind `auth` (a guest gets a plain 401 so the page just stays anonymous, not a login redirect), is `noindex`, throttled 30/min, `Cache-Control: no-store`, 404 when the secret isn't set, 403 for AI agent users. The JWT is hand-signed in `Quackback::ssoToken()` - no JWT package is installed and none was added.
4. **Client**: `resources/js/lib/quackback.ts`, called from `app.tsx`, asks for a token and calls `Quackback('identify', { ssoToken })` **once per full page load** when `auth.user` is present, and `Quackback('logout')` when it goes away. Logout is detected from the user disappearing from the shared props (covers the logout button, session expiry and a user switch) plus a `localStorage` flag so an anonymous visitor's own session isn't reset on every page. All of it is a no-op when `window.Quackback` doesn't exist (widget off).

## Things to know

- The widget ships name and email of signed-in users to a third party. The privacy policy (`config/legal.php`, `Pages/Legal/Privacy`) should say so before this is on in production.
- Never call `Quackback('identify', { id, email })` - the unverified shape is rejected; identity only ever goes through a signed ssoToken.
- Production: `QUACKBACK_URL` and `QUACKBACK_SIGNING_SECRET` are declared `preserve()` on the `web` service in `.railway/railway.ts` and must be set by hand there (see `docs/deploy-railway.md`). Only `web` needs them.
- `phpunit.xml` blanks both so a developer's `.env` can't change test results; tests turn the widget on with `config([...])` (`tests/Feature/QuackbackWidgetTest.php`).
- Instance admin: the Install page flips to "connected" once a page with the snippet has loaded; the launcher stays hidden if "Show on your website" is off there.
