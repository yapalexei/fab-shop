import DangerButton from '@/Components/DangerButton';
import type { JobTransition } from '@/Components/JobStatusActions';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export interface MovableJob {
    id: number;
    job_number: string;
    stage_label: string;
}

/**
 * Confirms a job status change before it's sent - used by the status
 * buttons and by drag-and-drop on the board alike, so no move ever happens
 * from a single stray click or drag. Explains what the move means (a
 * backward correction keeps its cost history; invoicing syncs to
 * QuickBooks; cancelling is permanent and takes an optional reason).
 */
export default function JobMoveDialog({
    job,
    transition,
    onClose,
}: {
    job: MovableJob | null;
    transition: JobTransition | null;
    onClose: () => void;
}) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setReason('');
    }, [job?.id, transition?.value]);

    const show = job !== null && transition !== null;

    // Keep rendering the last move while the dialog fades out, rather than
    // letting its contents vanish the instant it's dismissed.
    const [shown, setShown] = useState({ job, transition });
    useEffect(() => {
        if (job && transition) setShown({ job, transition });
    }, [job, transition]);

    const isCancel = shown.transition?.value === 'cancelled';

    const confirm = () => {
        if (!job || !transition || processing) return;

        setProcessing(true);
        router.patch(
            route('jobs.status', job.id),
            isCancel
                ? { cancel: true, reason }
                : { job_stage_id: transition.value },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    onClose();
                },
            },
        );
    };

    return (
        <Modal show={show} maxWidth="md" onClose={onClose}>
            {shown.job && shown.transition && (
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        {isCancel
                            ? `Cancel ${shown.job.job_number}?`
                            : shown.transition.is_forward
                              ? `Move ${shown.job.job_number} to ${shown.transition.label}?`
                              : `Move ${shown.job.job_number} back to ${shown.transition.label}?`}
                    </h2>

                    <p className="mt-2 text-sm text-gray-600">
                        Currently at{' '}
                        <span className="font-medium">
                            {shown.job.stage_label}
                        </span>
                        .
                        {!isCancel &&
                            !shown.transition.is_forward &&
                            ' Material and time already used stay on the job - the move is recorded in its history.'}
                        {shown.transition.role === 'invoiced' &&
                            ' It will be invoiced and, if QuickBooks is connected, the invoice pushed there.'}
                    </p>

                    {isCancel && (
                        <>
                            <p className="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">
                                A cancelled job can't be moved again - this
                                can't be undone.
                            </p>
                            <label className="mt-4 block text-sm font-medium text-gray-700">
                                Reason (optional)
                                <textarea
                                    className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                                    rows={2}
                                    value={reason}
                                    maxLength={1000}
                                    onChange={(e) => setReason(e.target.value)}
                                />
                            </label>
                        </>
                    )}

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            onClick={onClose}
                            disabled={processing}
                        >
                            Keep as is
                        </SecondaryButton>
                        {isCancel ? (
                            <DangerButton
                                onClick={confirm}
                                disabled={processing}
                            >
                                Cancel job
                            </DangerButton>
                        ) : (
                            <PrimaryButton
                                onClick={confirm}
                                disabled={processing}
                            >
                                Move to {shown.transition.label}
                            </PrimaryButton>
                        )}
                    </div>
                </div>
            )}
        </Modal>
    );
}
