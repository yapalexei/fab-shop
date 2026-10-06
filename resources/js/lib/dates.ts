// Calendar-day helpers for the job calendar and timeline. The server sends
// plain 'YYYY-MM-DD' strings already on the shop's wall clock, so days are
// handled as whole-day numbers (UTC midnight / 86400000) - no timezone or
// daylight-saving arithmetic can shift a job by a day.

const DAY_MS = 86_400_000;

export const toDay = (iso: string): number => {
    const [y, m, d] = iso.split('-').map(Number);
    return Date.UTC(y, m - 1, d) / DAY_MS;
};

export const fromDay = (day: number): string =>
    new Date(day * DAY_MS).toISOString().slice(0, 10);

/** Monday of the week containing `day` (days since epoch; 1970-01-01 was a Thursday). */
export const startOfWeek = (day: number): number => day - ((day + 3) % 7);

export const dayOfMonth = (day: number): number =>
    new Date(day * DAY_MS).getUTCDate();

export const monthStart = (day: number): number => {
    const d = new Date(day * DAY_MS);
    return Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1) / DAY_MS;
};

export const addMonths = (day: number, months: number): number => {
    const d = new Date(day * DAY_MS);
    return Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + months, 1) / DAY_MS;
};

export const monthLabel = (day: number): string =>
    new Date(day * DAY_MS).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    });

export const shortDate = (day: number): string =>
    new Date(day * DAY_MS).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });

export const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
