import { router } from '@inertiajs/react';

export interface JobTransition {
    value: string;
    label: string;
    is_forward: boolean;
}

/**
 * Renders whichever status transitions are currently legal for a job (see
 * Job::canTransitionTo) as buttons - the forward step (if any) is styled as
 * the primary action, backward corrections are secondary, and cancelling
 * always prompts for a reason since it's the one irreversible action here.
 */
export default function JobStatusActions({
    jobId,
    transitions,
}: {
    jobId: number;
    transitions: JobTransition[];
}) {
    const apply = (transition: JobTransition) => {
        if (transition.value === 'cancelled') {
            const reason = prompt(
                'Reason for cancelling this job (optional):',
            );
            if (reason === null) return;
            router.patch(
                route('jobs.status', jobId),
                { status: transition.value, reason },
                { preserveScroll: true },
            );
            return;
        }

        router.patch(
            route('jobs.status', jobId),
            { status: transition.value },
            { preserveScroll: true },
        );
    };

    if (transitions.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {transitions.map((transition) => (
                <button
                    key={transition.value}
                    type="button"
                    onClick={() => apply(transition)}
                    className={
                        transition.value === 'cancelled'
                            ? 'rounded-md border border-red-300 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50'
                            : transition.is_forward
                              ? 'rounded-md bg-gray-800 px-2 py-1 text-xs font-semibold text-white hover:bg-gray-700'
                              : 'rounded-md border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50'
                    }
                >
                    {transition.value === 'cancelled'
                        ? 'Cancel'
                        : transition.is_forward
                          ? `→ ${transition.label}`
                          : `← ${transition.label}`}
                </button>
            ))}
        </div>
    );
}
