# Security controls and runbooks

What is in place, what the owner must check by hand, and what to do when something goes wrong. Written as the starting evidence for a SOC 2 / ISO 27001 effort - keep it true: when a control changes, change this file.

## Controls in the code

| Control | Where |
|---|---|
| Tenant isolation (global scope + per-model isolation tests) | `BelongsToShop`, CLAUDE.md "Multi-tenancy" |
| Per-resource permissions, per-member overrides | `ShopPermissions`, `User::shopPermissions()` |
| Optional TOTP two-factor sign-in, hashed single-use recovery codes, replay protection, lockout | `TwoFactor`, `TwoFactorChallengeController`, `TwoFactorSettingsController` |
| Two-factor *requirement* by role with a grace period: platform default, per-shop override (incl. off), email + in-app reminder, enforced after the deadline | `TwoFactorPolicy`, `EnsureTwoFactorSetup`, Platform admin > Security > Two-factor requirement, Shop > Security > Two-factor sign-in |
| Security log screens: all shops (platform admin), own shop (owner) | `SecurityLogQuery`, `/platform-admin/security`, `/shop/security` |
| Content-Security-Policy (nonce-based) and baseline headers | `SecurityHeaders`, `config/security.php` |
| Shop data export (zip of CSVs + files) and scheduled erasure with a grace period | `BuildShopDataExport`, `PurgeShop`, `TenantTables`, Shop settings |
| Login throttling (5 tries per email+IP) | `LoginRequest` |
| Security log: sign-in/out, failures, lockout, password and 2FA changes, access and role changes, invitations, AI connection/token changes, platform-admin actions, account deletion | `SecurityLog`, `LogAuthenticationEvents`; activity log, log name `security` |
| Other sessions signed out on password change | `AuthenticateSession`, `PasswordController` |
| Encrypted sessions, secure cookies (production), HSTS and baseline headers | `config/session.php`, `.railway/railway.ts`, `SecurityHeaders` |
| Dependency audit on every PR/push and weekly; Dependabot update PRs | `.github/workflows/security.yml`, `.github/dependabot.yml` |
| Secrets (AI keys) encrypted, write-only, never logged | `ShopAiSetting`, CLAUDE.md "AI assistant" |

Read the security log in Platform admin > Security > Security log (owners: Shop > Security > Audit log). Entries carry the user (causer), IP, user agent and, where relevant, `shop_id` / `target_shop_id`. There is no retention limit yet - see "Not done".

Erasing a shop deliberately keeps: the security log (including a "shop deleted" entry) and consent records, as the audit trail. It does not delete people's accounts.

## Backups and restore (verify - not yet proven)

Customer data lives in Railway Postgres (plus the S3 bucket for photos, PDFs and imports). Nothing in this repo configures or tests backups, so before relying on them:

1. In the Railway dashboard, confirm what backup the Postgres service has (schedule, retention) and that the bucket has versioning or an equivalent. Record the answers here.
2. **Do a restore drill** at least once, then every quarter: restore the latest backup into a *new* throwaway database, point a local `.env` at it, run `sail artisan migrate:status` and click through login, a quote and a job photo. Write the date, who did it and how long it took below.
3. Targets to decide and record: how much data loss is acceptable (RPO) and how long an outage (RTO).

Restore drills done: _none yet_.

## Incident response (suspected breach or data exposure)

1. **Contain**: suspend the affected shop (platform admin), revoke AI tokens (`/shop/ai`), rotate `APP_KEY`-adjacent secrets as needed (changing `APP_KEY` signs everyone out and breaks encrypted columns - plan it, don't improvise), rotate the Railway, Resend and bucket credentials.
2. **Preserve**: export the `security` activity log and Railway deploy/request logs before they roll off.
3. **Assess**: which shops and which data (the log's `shop_id` and the `mcp_requests` / `ai_requests` tables help). Cross-tenant exposure is the worst case.
4. **Notify**: affected shop owners without undue delay; regulators and customers as the law and contracts require (decide with counsel - not covered here).
5. **Learn**: write down cause, timeline and the control that would have caught it; fix it and update this file.

## Access policy (minimum)

- Platform-admin access (`users.is_platform_admin`) is for named staff only, granted with `app:grant-platform-admin`, reviewed quarterly. Every platform-admin change to a shop is in the security log.
- Production access (Railway, bucket, DNS, Resend) uses individual accounts with two-factor on; no shared logins.
- Offboarding: remove the person from Railway, GitHub, Resend and the registrar the same day; revoke their platform-admin flag.

## Not done (known gaps)

- Two-factor is optional by default; the platform admin or a shop owner can require it by role (nobody is required until one of them turns it on). Platform admins themselves are only covered if they sit in a shop the policy covers.
- No retention/rotation policy for the security log or for `mcp_requests` / `ai_requests`.
- The CSP is enforced but was only tested by assertions, not clicked through in a browser; it has no violation-report endpoint, so a block shows only in the browser console. Roll back with `CSP_MODE=report-only`.
- Shop erasure has no backup story: after the purge the data is gone from the database, but copies persist in database backups until they expire - decide and document that retention. Billing/subscriptions are not touched by deletion (there is no payment integration yet).
- No penetration test, no formal risk register, no vendor reviews, no written policy set - the organizational half of SOC 2 / ISO 27001.
- No re-acceptance gate when the privacy policy version changes.
