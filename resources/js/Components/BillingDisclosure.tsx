import { useShopDateTime } from '@/lib/datetime';
import { formatMoney } from '@/lib/money';

export interface BillingQuote {
    charge_cents: number;
    credit_cents: number;
    period_price_cents: number;
    interval: 'monthly' | 'annual';
    renews_at: string;
    prorated: boolean;
    days_left: number | null;
    starts_new_period: boolean;
}

/**
 * The "what will I be charged" notice shown before anyone commits to a
 * subscription change: the amount due now, what it costs every period after
 * that and when, and the cancellation terms. Wording matters here - keep it
 * in step with how App\Support\Billing actually works.
 */
export default function BillingDisclosure({ quote }: { quote: BillingQuote }) {
    const formatDate = useShopDateTime({ dateStyle: 'medium' });
    const per = quote.interval === 'annual' ? 'year' : 'month';
    const renews = formatDate(quote.renews_at);

    return (
        <div className="space-y-2 rounded-md bg-gray-50 p-3 text-sm text-gray-700">
            <p>
                {quote.charge_cents > 0 ? (
                    <>
                        You'll be charged{' '}
                        <strong>{formatMoney(quote.charge_cents)} today</strong>
                        {quote.prorated &&
                            quote.days_left !== null &&
                            ` (prorated for the ${quote.days_left} day${quote.days_left === 1 ? '' : 's'} left in this billing period)`}
                    </>
                ) : (
                    <>
                        <strong>No charge today</strong>
                        {quote.credit_cents > 0 &&
                            ` - ${formatMoney(quote.credit_cents)} of unused time is credited to your next invoice`}
                    </>
                )}
                , then{' '}
                <strong>
                    {formatMoney(quote.period_price_cents)}/{per}
                </strong>{' '}
                starting <strong>{renews}</strong>.
            </p>
            <p className="text-xs text-gray-500">
                Charged immediately. Cancel any time and you keep access until
                the end of the period you've paid for. We don't refund the
                current period.
            </p>
        </div>
    );
}
