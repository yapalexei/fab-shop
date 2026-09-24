import JobMoveDialog, { MovableJob } from '@/Components/JobMoveDialog';
import { useState } from 'react';

export interface JobTransition {
    value: string;
    label: string;
    is_forward: boolean;
}

/**
 * Renders whichever status transitions are currently legal for a job (see
 * Job::canTransitionTo) and that this user may make - the forward step (if
 * any) is styled as the primary action, backward corrections are
 * secondary, and cancelling is red. Every one opens a confirmation dialog
 * rather than moving the job straight away.
 */
export default function JobStatusActions({
    job,
    transitions,
}: {
    job: MovableJob;
    transitions: JobTransition[];
}) {
    const [pending, setPending] = useState<JobTransition | null>(null);

    if (transitions.length === 0) {
        return null;
    }

    return (
        <>
            <div className="flex flex-wrap gap-2">
                {transitions.map((transition) => (
                    <button
                        key={transition.value}
                        type="button"
                        onClick={() => setPending(transition)}
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

            <JobMoveDialog
                job={pending ? job : null}
                transition={pending}
                onClose={() => setPending(null)}
            />
        </>
    );
}
