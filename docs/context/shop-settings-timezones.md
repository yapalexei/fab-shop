# Shop settings and timezones

Per-shop settings (`shops.timezone`, `stale_quote_days`, `win_rate_window_days`) are nullable; null means "use `config('shop.defaults.*')`", read through `Shop::localTimezone()` / `staleQuoteDays()` / `winRateWindowDays()` - never the raw column.

Everything is stored in UTC. Anything that depends on the shop's calendar (month boundaries, "today", dates shown to people, invoice dates) goes through `Shop::localNow()` / `localTimezone()`; the frontend formats timestamps with `useShopDateTime()` (`resources/js/lib/datetime.ts`), which uses the shared `shop.timezone` rather than the viewer's browser. The trial window is the deliberate exception: `trial_started_at`/`trial_ends_at` are fixed UTC instants set at signup (`Shop::TRIAL_DAYS` x 24h), never derived from the timezone, so changing timezone can't lengthen a trial. Two traps, both hit while building this:
- Call `->utc()` before assigning a shop-local Carbon to a model attribute - Eloquent stores the wall-clock digits and drops the offset.
- Call `->copy()` before `->setTimezone()` on a model's date attribute - Carbon dates are mutable, so converting in place changes the attribute and a later `save()` writes the local time as if it were UTC.
