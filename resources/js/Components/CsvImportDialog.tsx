import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';

interface ImportError {
    row: number;
    messages: string[];
}

interface ImportStatus {
    id: number;
    status: 'pending' | 'completed' | 'failed';
    created_count: number;
    updated_count: number;
    skipped_count: number;
    errors: ImportError[] | null;
}

type Phase = 'form' | 'uploading' | 'processing' | 'done';

/**
 * Owner-only CSV import, dropped onto any catalog resource's Index page.
 * Uploads via plain axios (not Inertia's router) so the response - the new
 * import's id - comes back synchronously to start polling with, since this
 * dialog stays open on the current page rather than navigating anywhere.
 */
export default function CsvImportDialog({
    resource,
    label,
    reloadProps,
}: {
    resource: string;
    label: string;
    /** Prop name(s) on the current Index page to refresh after a successful import. */
    reloadProps: string[];
}) {
    const [open, setOpen] = useState(false);
    const [phase, setPhase] = useState<Phase>('form');
    const [overwrite, setOverwrite] = useState(false);
    const [fileError, setFileError] = useState<string | null>(null);
    const [result, setResult] = useState<ImportStatus | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const pollTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return () => {
            if (pollTimer.current) clearTimeout(pollTimer.current);
        };
    }, []);

    const reset = () => {
        setPhase('form');
        setOverwrite(false);
        setFileError(null);
        setResult(null);
        if (fileInput.current) fileInput.current.value = '';
    };

    const close = () => {
        setOpen(false);
        reset();
    };

    const poll = (importId: number) => {
        axios.get(route('imports.show', importId)).then(({ data }) => {
            if (data.status === 'pending') {
                pollTimer.current = setTimeout(() => poll(importId), 1200);
                return;
            }

            setResult(data);
            setPhase('done');

            // Newly imported rows won't show up until the page's own props
            // are refreshed.
            if (data.created_count > 0 || data.updated_count > 0) {
                router.reload({ only: reloadProps });
            }
        });
    };

    const submit = () => {
        const file = fileInput.current?.files?.[0];

        if (!file) {
            setFileError('Choose a CSV file first.');
            return;
        }

        setFileError(null);
        setPhase('uploading');

        const data = new FormData();
        data.append('file', file);
        data.append('overwrite', overwrite ? '1' : '0');

        axios
            .post(route('imports.store', resource), data)
            .then(({ data }) => {
                setPhase('processing');
                poll(data.id);
            })
            .catch((error) => {
                setFileError(
                    error.response?.data?.errors?.file?.[0] ??
                        'Something went wrong uploading that file.',
                );
                setPhase('form');
            });
    };

    return (
        <>
            <SecondaryButton onClick={() => setOpen(true)}>
                Import CSV
            </SecondaryButton>

            <Modal show={open} onClose={close} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Import {label}
                    </h2>

                    {phase === 'form' && (
                        <div className="mt-4 space-y-4">
                            <p className="text-sm text-gray-600">
                                Upload a CSV of {label.toLowerCase()} to add
                                or update them in bulk.{' '}
                                <a
                                    href={route('imports.template', resource)}
                                    className="text-indigo-600 hover:text-indigo-900"
                                >
                                    Download the template
                                </a>{' '}
                                to see the expected columns.
                            </p>

                            <input
                                ref={fileInput}
                                type="file"
                                accept=".csv,text/csv"
                                className="block w-full text-sm text-gray-600 file:me-4 file:rounded-md file:border file:border-gray-300 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-gray-600 hover:file:bg-gray-50"
                            />
                            {fileError && (
                                <p className="text-sm text-red-600">
                                    {fileError}
                                </p>
                            )}

                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    checked={overwrite}
                                    onChange={(e) =>
                                        setOverwrite(e.target.checked)
                                    }
                                />
                                Overwrite existing records with the same name
                            </label>
                            <p className="text-xs text-gray-500">
                                Matched by name only (case-insensitive). Left
                                unchecked, a row matching an existing name is
                                skipped rather than changed.
                            </p>

                            <div className="flex justify-end gap-3 pt-2">
                                <SecondaryButton onClick={close}>
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton onClick={submit}>
                                    Import
                                </PrimaryButton>
                            </div>
                        </div>
                    )}

                    {(phase === 'uploading' || phase === 'processing') && (
                        <p className="mt-4 text-sm text-gray-600">
                            {phase === 'uploading'
                                ? 'Uploading...'
                                : 'Processing your file - this page will update automatically.'}
                        </p>
                    )}

                    {phase === 'done' && result && (
                        <div className="mt-4 space-y-3">
                            {result.status === 'failed' ? (
                                <p className="text-sm text-red-600">
                                    {result.errors?.[0]?.messages?.[0] ??
                                        "The file couldn't be imported."}
                                </p>
                            ) : (
                                <p className="text-sm text-gray-700">
                                    {result.created_count} added,{' '}
                                    {result.updated_count} updated,{' '}
                                    {result.skipped_count} skipped (already
                                    existed).
                                </p>
                            )}

                            {result.errors && result.errors.length > 0 && (
                                <div className="max-h-48 overflow-y-auto rounded-md bg-amber-50 p-3">
                                    <p className="text-xs font-semibold uppercase tracking-wider text-amber-800">
                                        {result.status === 'failed'
                                            ? 'Fix this and re-upload'
                                            : `${result.errors.length} row${result.errors.length === 1 ? '' : 's'} skipped`}
                                    </p>
                                    <ul className="mt-2 space-y-1 text-sm text-amber-800">
                                        {result.errors.map((error) => (
                                            <li key={error.row}>
                                                {error.row > 0 &&
                                                    `Row ${error.row}: `}
                                                {error.messages.join(' ')}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <div className="flex justify-end gap-3 pt-2">
                                {result.errors && result.errors.length > 0 && (
                                    <SecondaryButton onClick={reset}>
                                        Import another file
                                    </SecondaryButton>
                                )}
                                <PrimaryButton onClick={close}>
                                    Done
                                </PrimaryButton>
                            </div>
                        </div>
                    )}
                </div>
            </Modal>
        </>
    );
}
