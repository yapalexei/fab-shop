/**
 * What a plan would cost a shop, monthly and annually. Pure arithmetic over
 * prices the server sends, mirroring Billing::periodPriceCents(): an annual
 * bill is the monthly figure times the number of months charged (so every
 * component scales the same way and the annual total is just monthly x N).
 * All amounts are cents.
 */

export interface CostPlan {
    monthlyCents: number;
    /** Included seats; null = unlimited. */
    seats: number | null;
    /** Price of one extra seat per month; null = extra seats aren't sold. */
    extraSeatCents: number | null;
    /** Keys of the features the plan includes. */
    features: string[];
}

export interface CostFeature {
    key: string;
    label: string;
    /** What the feature costs per month as a standalone add-on. */
    addonCents: number;
}

export interface PlanCost {
    /** False when the plan can't seat that many people. */
    available: boolean;
    /** Why it isn't available, ready to show. */
    reason: string | null;
    planCents: number;
    extraSeats: number;
    extraSeatsCents: number;
    /** Needed features the plan doesn't include, bought as add-ons. */
    addons: { key: string; label: string; cents: number }[];
    addonsCents: number;
    monthlyCents: number;
    annualCents: number;
}

export function planCost(
    plan: CostPlan,
    features: CostFeature[],
    people: number,
    needed: string[],
    annualMonths: number,
): PlanCost {
    const extraSeats =
        plan.seats === null ? 0 : Math.max(0, Math.floor(people) - plan.seats);
    const available = extraSeats === 0 || plan.extraSeatCents !== null;
    const extraSeatsCents = extraSeats * (plan.extraSeatCents ?? 0);

    const addons = features
        .filter(
            (feature) =>
                needed.includes(feature.key) &&
                !plan.features.includes(feature.key),
        )
        .map((feature) => ({
            key: feature.key,
            label: feature.label,
            cents: feature.addonCents,
        }));
    const addonsCents = addons.reduce((sum, addon) => sum + addon.cents, 0);
    const monthlyCents = plan.monthlyCents + extraSeatsCents + addonsCents;

    return {
        available,
        reason: available
            ? null
            : `Fits up to ${plan.seats} ${plan.seats === 1 ? 'person' : 'people'}`,
        planCents: plan.monthlyCents,
        extraSeats,
        extraSeatsCents,
        addons,
        addonsCents,
        monthlyCents,
        annualCents: monthlyCents * annualMonths,
    };
}

/** Index of the cheapest plan that is available, or null if none is. */
export function cheapestIndex(
    costs: PlanCost[],
    by: 'monthlyCents' | 'annualCents',
): number | null {
    let best: number | null = null;

    costs.forEach((cost, index) => {
        if (cost.available && (best === null || cost[by] < costs[best][by])) {
            best = index;
        }
    });

    return best;
}
