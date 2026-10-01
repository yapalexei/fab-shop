export type Interval = 'monthly' | 'annual';

/** Monthly / Annual switch, with what annual saves spelled out. */
export default function BillingIntervalToggle({
    value,
    onChange,
    annualMonthsCharged,
}: {
    value: Interval;
    onChange: (interval: Interval) => void;
    annualMonthsCharged: number;
}) {
    const free = 12 - annualMonthsCharged;
    const button = (interval: Interval, label: string) => (
        <button
            type="button"
            onClick={() => onChange(interval)}
            aria-pressed={value === interval}
            className={`px-4 py-1.5 text-sm font-medium first:rounded-s-md last:rounded-e-md ${
                value === interval
                    ? 'bg-indigo-600 text-white'
                    : 'bg-white text-gray-700 hover:bg-gray-50'
            }`}
        >
            {label}
        </button>
    );

    return (
        <div className="inline-flex items-center gap-3">
            <div className="inline-flex divide-x divide-gray-300 rounded-md border border-gray-300 shadow-sm">
                {button('monthly', 'Monthly')}
                {button(
                    'annual',
                    free > 0 ? `Annual - ${free} months free` : 'Annual',
                )}
            </div>
        </div>
    );
}
