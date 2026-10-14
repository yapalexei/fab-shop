import InputLabel from '@/Components/InputLabel';
import PlanComparisonTable, {
    ComparisonFeature,
    ComparisonPlan,
} from '@/Components/PlanComparisonTable';
import TextInput from '@/Components/TextInput';
import { cheapestIndex, planCost } from '@/lib/planCost';
import { useMemo, useState } from 'react';

/**
 * "What would each plan cost us?": the owner says how many people and which
 * features the shop needs, and every plan is priced for that - the plan itself,
 * extra people, and add-ons for anything it doesn't include - monthly and
 * yearly, with the lowest marked. Prices come from the server; nothing here
 * is charged.
 */
export default function PlanCostCalculator({
    plans,
    features,
    annualMonths,
    initialPeople,
    initialNeeded,
}: {
    plans: ComparisonPlan[];
    features: Required<ComparisonFeature>[];
    annualMonths: number;
    initialPeople: number;
    initialNeeded: string[];
}) {
    const [people, setPeople] = useState(String(Math.max(1, initialPeople)));
    const [needed, setNeeded] = useState<string[]>(initialNeeded);

    const count = Math.max(1, Math.floor(Number(people)) || 1);

    const costs = useMemo(
        () =>
            plans.map((plan) =>
                planCost(
                    {
                        monthlyCents: plan.monthlyCents,
                        seats: plan.seats,
                        extraSeatCents: plan.extraSeatCents,
                        features: plan.features,
                    },
                    features.map((f) => ({
                        key: f.key,
                        label: f.label,
                        addonCents: f.addonCents,
                    })),
                    count,
                    needed,
                    annualMonths,
                ),
            ),
        [plans, features, count, needed, annualMonths],
    );

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end gap-4">
                <div>
                    <InputLabel htmlFor="calc-people" value="People using the app" />
                    <TextInput
                        id="calc-people"
                        type="number"
                        min="1"
                        className="mt-1 block w-28"
                        value={people}
                        onChange={(e) => setPeople(e.target.value)}
                    />
                </div>
                <p className="max-w-xl pb-2 text-xs text-muted-foreground">
                    Tick the features your shop needs. A plan that doesn't
                    include one shows its add-on price, and the totals below
                    count it. Starting point: what you use today.
                </p>
            </div>

            <PlanComparisonTable
                caption="Cost of every plan for your shop"
                plans={plans}
                features={features}
                annualMonths={annualMonths}
                calculator={{
                    needed,
                    onToggleNeeded: (key, on) =>
                        setNeeded((current) =>
                            on
                                ? [...current, key]
                                : current.filter((k) => k !== key),
                        ),
                    costs,
                    cheapestMonthly: cheapestIndex(costs, 'monthlyCents'),
                    cheapestAnnual: cheapestIndex(costs, 'annualCents'),
                }}
            />
        </div>
    );
}
