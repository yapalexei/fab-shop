import JobMoveDialog, { MovableJob } from '@/Components/JobMoveDialog';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { useState } from 'react';

export interface JobTransition {
    value: string;
    label: string;
    is_forward: boolean;
    role: 'not_started' | 'shipped' | 'invoiced' | null;
}

function GearIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 20 20" fill="currentColor" className={className}>
            <path
                fillRule="evenodd"
                d="M8.34 1.804A1 1 0 019.32 1h1.36a1 1 0 01.98.804l.295 1.473c.497.144.971.342 1.416.587l1.255-.814a1 1 0 011.243.139l.962.962a1 1 0 01.14 1.243l-.815 1.255c.245.445.443.919.587 1.416l1.473.294a1 1 0 01.804.98v1.361a1 1 0 01-.804.98l-1.473.295a6.95 6.95 0 01-.587 1.416l.815 1.255a1 1 0 01-.14 1.243l-.962.962a1 1 0 01-1.243.14l-1.255-.815a6.95 6.95 0 01-1.416.587l-.294 1.473a1 1 0 01-.98.804H9.32a1 1 0 01-.98-.804l-.295-1.473a6.957 6.957 0 01-1.416-.587l-1.255.815a1 1 0 01-1.243-.14l-.962-.962a1 1 0 01-.139-1.243l.814-1.255a6.957 6.957 0 01-.587-1.416l-1.473-.295a1 1 0 01-.804-.98V9.32a1 1 0 01.804-.98l1.473-.295c.144-.497.342-.971.587-1.416l-.814-1.255a1 1 0 01.139-1.243l.962-.962a1 1 0 011.243-.139l1.255.814a6.957 6.957 0 011.416-.587l.295-1.473zM13 10a3 3 0 11-6 0 3 3 0 016 0z"
                clipRule="evenodd"
            />
        </svg>
    );
}

function transitionLabel(transition: JobTransition): string {
    return transition.value === 'cancelled'
        ? 'Cancel'
        : transition.is_forward
          ? `→ ${transition.label}`
          : `← ${transition.label}`;
}

/**
 * A compact picker for whichever status transitions are currently legal for
 * a job (see Job::canTransitionTo) and that this user may make. Picking an
 * option from the popover opens the confirmation dialog rather than moving
 * the job straight away, same as a drag-and-drop.
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
            <Popover className="relative inline-block">
                <PopoverButton
                    aria-label={`Move ${job.job_number}`}
                    className="inline-flex items-center gap-1 rounded-full border border-gray-200 py-0.5 pe-2 ps-1 text-xs text-gray-600 hover:bg-gray-50"
                >
                    <GearIcon className="h-4 w-4 shrink-0 text-gray-400" />
                    Move
                </PopoverButton>

                <PopoverPanel
                    anchor="bottom start"
                    className="z-10 mt-1 w-44 rounded-md border border-gray-200 bg-white py-1 shadow-lg focus:outline-none"
                >
                    {({ close }) => (
                        <>
                            {transitions.map((transition) => (
                                <button
                                    key={transition.value}
                                    type="button"
                                    onClick={() => {
                                        setPending(transition);
                                        close();
                                    }}
                                    className={`block w-full px-3 py-1.5 text-start text-sm hover:bg-gray-50 ${
                                        transition.value === 'cancelled'
                                            ? 'text-red-600'
                                            : 'text-gray-700'
                                    }`}
                                >
                                    {transitionLabel(transition)}
                                </button>
                            ))}
                        </>
                    )}
                </PopoverPanel>
            </Popover>

            <JobMoveDialog
                job={pending ? job : null}
                transition={pending}
                onClose={() => setPending(null)}
            />
        </>
    );
}
