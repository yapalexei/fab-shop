import { Checkbox } from '@/Components/ui/checkbox';
import { formatMoney } from '@/lib/money';
import { PlanCost } from '@/lib/planCost';
import { cn } from '@/lib/utils';
import { Check, Minus } from 'lucide-react';
import { ReactNode } from 'react';

export interface ComparisonPlan {
    id: string | number;
    name: string;
    /** Small line under the name ("Your plan", "14-day trial"). */
    note?: string;
    /** Outline colour for the plan the viewer is on or heading to. */
    highlight?: 'current' | 'next';
    /** The free-trial column: priced "Free for N days", no yearly price. */
    trialDays?: number;
    monthlyCents: number;
    /** Included seats; null = unlimited. */
    seats: number | null;
    extraSeatCents: number | null;
    /** Keys of the features it includes. */
    features: string[];
    /** Button or label under the column. */
    action?: ReactNode;
}

export interface ComparisonFeature {
    key: string;
    label: string;
    /** Standalone add-on price per month, when buying it separately is possible. */
    addonCents?: number;
}

/** What the optional cost calculator adds to the table. */
export interface CalculatorColumns {
    needed: string[];
    onToggleNeeded: (key: string, needed: boolean) => void;
    /** One per plan, in the same order. */
    costs: PlanCost[];
    cheapestMonthly: number | null;
    cheapestAnnual: number | null;
}

const ring: Record<NonNullable<ComparisonPlan['highlight']>, string> = {
    current: 'bg-indigo-50 dark:bg-indigo-950/40',
    next: 'bg-green-50 dark:bg-green-950/40',
};

/**
 * Every plan side by side: price (monthly and yearly), seats, and a tick for
 * each feature it includes. With `calculator`, a column of "I need this"
 * boxes is added, a feature a plan lacks shows what it costs as an add-on,
 * and the bottom rows total what each plan comes to for those needs.
 */
export default function PlanComparisonTable({
    plans,
    features,
    annualMonths,
    calculator,
    caption,
}: {
    plans: ComparisonPlan[];
    features: ComparisonFeature[];
    annualMonths: number;
    calculator?: CalculatorColumns;
    caption: string;
}) {
    const cell = (plan: ComparisonPlan, extra = '') =>
        cn(
            'px-3 py-2.5 text-center align-middle',
            plan.highlight && ring[plan.highlight],
            extra,
        );
    const labelCell =
        'sticky left-0 z-10 bg-background px-3 py-2.5 text-start text-sm font-medium';
    const section = 'border-t';

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full min-w-[40rem] border-collapse text-sm">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr>
                        <th scope="col" className={cn(labelCell, 'py-3')}>
                            <span className="sr-only">Plan</span>
                        </th>
                        {calculator && (
                            <th scope="col" className="px-3 py-3 text-xs font-medium text-muted-foreground">
                                I need
                            </th>
                        )}
                        {plans.map((plan) => (
                            <th
                                key={plan.id}
                                scope="col"
                                className={cell(plan, 'py-3 text-base font-semibold')}
                            >
                                {plan.name}
                                {plan.note && (
                                    <div className="text-xs font-normal text-muted-foreground">
                                        {plan.note}
                                    </div>
                                )}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    <tr className={section}>
                        <th scope="row" className={labelCell}>Price per month</th>
                        {calculator && <td />}
                        {plans.map((plan) => (
                            <td key={plan.id} className={cell(plan, 'font-semibold')}>
                                {plan.trialDays !== undefined
                                    ? `Free for ${plan.trialDays} days`
                                    : plan.monthlyCents === 0
                                      ? 'Free'
                                      : formatMoney(plan.monthlyCents)}
                            </td>
                        ))}
                    </tr>
                    <tr className={section}>
                        <th scope="row" className={labelCell}>Price per year</th>
                        {calculator && <td />}
                        {plans.map((plan) => (
                            <td key={plan.id} className={cell(plan)}>
                                {plan.trialDays !== undefined || plan.monthlyCents === 0 ? (
                                    <Minus className="mx-auto size-4 text-muted-foreground/50" aria-label="Not applicable" />
                                ) : (
                                    <>
                                        {formatMoney(plan.monthlyCents * annualMonths)}
                                        <div className="text-xs text-muted-foreground">
                                            {annualMonths} months charged
                                        </div>
                                    </>
                                )}
                            </td>
                        ))}
                    </tr>
                    <tr className={section}>
                        <th scope="row" className={labelCell}>People included</th>
                        {calculator && <td />}
                        {plans.map((plan) => (
                            <td key={plan.id} className={cell(plan)}>
                                {plan.seats === null ? 'Unlimited' : `Up to ${plan.seats}`}
                            </td>
                        ))}
                    </tr>
                    <tr className={section}>
                        <th scope="row" className={labelCell}>Each extra person</th>
                        {calculator && <td />}
                        {plans.map((plan) => (
                            <td key={plan.id} className={cell(plan)}>
                                {plan.seats === null ? (
                                    <span className="text-muted-foreground">-</span>
                                ) : plan.extraSeatCents === null ? (
                                    <span className="text-muted-foreground">Not offered</span>
                                ) : (
                                    `${formatMoney(plan.extraSeatCents)}/mo`
                                )}
                            </td>
                        ))}
                    </tr>

                    {features.map((feature) => {
                        const needed = calculator?.needed.includes(feature.key) ?? false;

                        return (
                            <tr key={feature.key} className={section}>
                                <th scope="row" className={cn(labelCell, 'font-normal')}>
                                    {feature.label}
                                </th>
                                {calculator && (
                                    <td className="px-3 text-center">
                                        <Checkbox
                                            aria-label={`I need ${feature.label}`}
                                            checked={needed}
                                            onCheckedChange={(checked) =>
                                                calculator.onToggleNeeded(feature.key, checked === true)
                                            }
                                        />
                                    </td>
                                )}
                                {plans.map((plan) => {
                                    const included = plan.features.includes(feature.key);

                                    return (
                                        <td key={plan.id} className={cell(plan)}>
                                            {included ? (
                                                <Check className="mx-auto size-4 text-green-600" aria-label="Included" />
                                            ) : calculator && feature.addonCents !== undefined ? (
                                                <span
                                                    className={cn(
                                                        'text-xs',
                                                        needed ? 'font-medium text-foreground' : 'text-muted-foreground',
                                                    )}
                                                    title="Bought as a separate add-on"
                                                >
                                                    +{formatMoney(feature.addonCents)}/mo
                                                </span>
                                            ) : (
                                                <Minus className="mx-auto size-4 text-muted-foreground/50" aria-label="Not included" />
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        );
                    })}

                    {calculator && (
                        <>
                            <tr className="border-t-2">
                                <th scope="row" className={labelCell}>Plan</th>
                                <td />
                                {plans.map((plan, i) => (
                                    <td key={plan.id} className={cell(plan)}>
                                        {formatMoney(calculator.costs[i].planCents)}
                                    </td>
                                ))}
                            </tr>
                            <tr className={section}>
                                <th scope="row" className={labelCell}>Extra people</th>
                                <td />
                                {plans.map((plan, i) => {
                                    const cost = calculator.costs[i];

                                    return (
                                        <td key={plan.id} className={cell(plan)}>
                                            {!cost.available ? (
                                                <span className="text-xs text-destructive">{cost.reason}</span>
                                            ) : cost.extraSeats === 0 ? (
                                                <span className="text-muted-foreground">-</span>
                                            ) : (
                                                <>
                                                    {formatMoney(cost.extraSeatsCents)}
                                                    <div className="text-xs text-muted-foreground">
                                                        {cost.extraSeats} extra
                                                    </div>
                                                </>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                            <tr className={section}>
                                <th scope="row" className={labelCell}>Add-ons you'd still need</th>
                                <td />
                                {plans.map((plan, i) => {
                                    const cost = calculator.costs[i];

                                    return (
                                        <td
                                            key={plan.id}
                                            className={cell(plan)}
                                            title={cost.addons.map((a) => a.label).join(', ')}
                                        >
                                            {cost.addons.length === 0 ? (
                                                <span className="text-muted-foreground">-</span>
                                            ) : (
                                                <>
                                                    {formatMoney(cost.addonsCents)}
                                                    <div className="text-xs text-muted-foreground">
                                                        {cost.addons.length} add-on{cost.addons.length === 1 ? '' : 's'}
                                                    </div>
                                                </>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                            {(['monthlyCents', 'annualCents'] as const).map((by) => {
                                const best = by === 'monthlyCents' ? calculator.cheapestMonthly : calculator.cheapestAnnual;

                                return (
                                    <tr key={by} className="border-t-2 font-semibold">
                                        <th scope="row" className={labelCell}>
                                            {by === 'monthlyCents' ? 'Total, billed monthly' : 'Total, billed yearly'}
                                        </th>
                                        <td />
                                        {plans.map((plan, i) => {
                                            const cost = calculator.costs[i];
                                            const isBest = best === i;

                                            return (
                                                <td key={plan.id} className={cell(plan, isBest ? 'text-green-700 dark:text-green-400' : '')}>
                                                    {!cost.available ? (
                                                        <span className="text-muted-foreground">-</span>
                                                    ) : (
                                                        <>
                                                            {formatMoney(cost[by])}
                                                            <span className="text-xs font-normal text-muted-foreground">
                                                                {by === 'monthlyCents' ? '/mo' : '/yr'}
                                                            </span>
                                                            {by === 'annualCents' && cost.annualCents > 0 && (
                                                                <div className="text-xs font-normal text-muted-foreground">
                                                                    about {formatMoney(Math.round(cost.annualCents / 12))}/mo
                                                                </div>
                                                            )}
                                                            {isBest && <div className="text-xs">Lowest for you</div>}
                                                        </>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                );
                            })}
                        </>
                    )}

                    {plans.some((plan) => plan.action) && (
                        <tr className="border-t">
                            <td className="sticky left-0 z-10 bg-background" />
                            {calculator && <td />}
                            {plans.map((plan) => (
                                <td key={plan.id} className={cell(plan, 'py-3')}>
                                    {plan.action}
                                </td>
                            ))}
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
