export function formatCents(cents: number): string {
    return (cents / 100).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

/** Like formatCents, but keeps the cents when there are any (prorated amounts). */
export function formatMoney(cents: number): string {
    return (cents / 100).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: cents % 100 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    });
}
