import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';
import { Alert } from '@/Components/ui/alert';

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

interface Preview {
    headers: string[];
    sample: string[][];
    columns: { name: string; required: boolean }[];
    suggested: Record<string, number | null>;
}

type Phase = 'form' | 'uploading' | 'mapping' | 'processing' | 'done';

/** The file already has every column of our template, spelled the same. */
const matchesTemplate = (preview: Preview) => {
    const headers = preview.headers.map((h) => h.trim().toLowerCase());

    return preview.columns.every((c) => headers.includes(c.name));
};

/**
 * Owner-only CSV import, dropped onto any catalog resource's Index page.
 * Uploads via plain axios (not Inertia's router) so the response - the new
 * import's id - comes back synchronously to start polling with, since this
 * dialog stays open on the current page rather than navigating anywhere.
 *
 * A file that already has our template's columns imports straight away;
 * any other (a supplier's export, say) gets a mapping step first, where the
 * owner says which of their columns holds what.
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
    const [preview, setPreview] = useState<Preview | null>(null);
    const [mapping, setMapping] = useState<Record<string, string>>({});
    const [file, setFile] = useState<File | null>(null);
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
        setPreview(null);
        setMapping({});
        setFile(null);
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

    const errorMessage = (error: any) => {
        const errors = error.response?.data?.errors;

        return (
            (errors && (Object.values(errors).flat()[0] as string)) ??
            'Something went wrong uploading that file.'
        );
    };

    const upload = (chosen: File, chosenMapping?: Record<string, string>) => {
        setFileError(null);
        setPhase('uploading');

        const data = new FormData();
        data.append('file', chosen);
        data.append('overwrite', overwrite ? '1' : '0');
        Object.entries(chosenMapping ?? {}).forEach(([column, index]) =>
            data.append(`mapping[${column}]`, index),
        );

        axios
            .post(route('imports.store', resource), data)
            .then(({ data }) => {
                setPhase('processing');
                poll(data.id);
            })
            .catch((error) => {
                setFileError(errorMessage(error));
                setPhase(chosenMapping ? 'mapping' : 'form');
            });
    };

    const submit = () => {
        const chosen = fileInput.current?.files?.[0];

        if (!chosen) {
            setFileError('Choose a CSV file first.');
            return;
        }

        setFileError(null);
        setPhase('uploading');
        setFile(chosen);

        const data = new FormData();
        data.append('file', chosen);

        axios
            .post(route('imports.preview', resource), data)
            .then(({ data }: { data: Preview }) => {
                if (matchesTemplate(data)) {
                    upload(chosen);
                    return;
                }

                setPreview(data);
                setMapping(
                    Object.fromEntries(
                        data.columns.map((c) => [
                            c.name,
                            data.suggested[c.name] === null ||
                            data.suggested[c.name] === undefined
                                ? ''
                                : String(data.suggested[c.name]),
                        ]),
                    ),
                );
                setPhase('mapping');
            })
            .catch((error) => {
                setFileError(errorMessage(error));
                setPhase('form');
            });
    };

    const missingRequired =
        preview?.columns.filter((c) => c.required && mapping[c.name] === '') ??
        [];

    return (
        <>
            <SecondaryButton onClick={() => setOpen(true)}>
                Import CSV
            </SecondaryButton>

            <Modal show={open} onClose={close} maxWidth={phase === 'mapping' ? '2xl' : 'md'}>
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
                                    className="text-indigo-600 hover:underline"
                                >
                                    Download the template
                                </a>{' '}
                                to see the expected columns, or upload a file
                                with different columns (a supplier's export,
                                say) and match them up on the next step.
                            </p>

                            <input
                                ref={fileInput}
                                type="file"
                                accept=".csv,text/csv"
                                className="block w-full text-sm text-gray-600 file:me-4 file:rounded-md file:border file:border-gray-300 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-gray-600 hover:file:bg-accent"
                            />
                            {fileError && (
                                <p className="text-sm text-red-600">
                                    {fileError}
                                </p>
                            )}

                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    className="rounded-sm border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500"
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

                    {phase === 'mapping' && preview && (
                        <div className="mt-4 space-y-4">
                            <p className="text-sm text-gray-600">
                                Your file's columns don't match the template.
                                Choose which column of{' '}
                                <span className="font-medium">
                                    {file?.name}
                                </span>{' '}
                                holds each field. We've guessed where we could;
                                anything set to "Don't import" is left blank.
                            </p>

                            <div className="overflow-x-auto rounded-md border border-gray-200">
                                <table className="min-w-full divide-y text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                                Field
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                                Column in your file
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                                Example
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {preview.columns.map((column) => {
                                            const chosen = mapping[column.name];
                                            const example =
                                                chosen === ''
                                                    ? ''
                                                    : (preview.sample[0]?.[
                                                          Number(chosen)
                                                      ] ?? '');

                                            return (
                                                <tr key={column.name}>
                                                    <td className="px-3 py-2 align-top">
                                                        {column.name}
                                                        {column.required && (
                                                            <span className="ms-1 text-red-600">
                                                                *
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 align-top">
                                                        <select
                                                            aria-label={`Column for ${column.name}`}
                                                            className="block w-48 rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                                            value={chosen}
                                                            onChange={(e) =>
                                                                setMapping({
                                                                    ...mapping,
                                                                    [column.name]:
                                                                        e.target
                                                                            .value,
                                                                })
                                                            }
                                                        >
                                                            <option value="">
                                                                Don't import
                                                            </option>
                                                            {preview.headers.map(
                                                                (header, i) => (
                                                                    <option
                                                                        key={i}
                                                                        value={String(
                                                                            i,
                                                                        )}
                                                                    >
                                                                        {header ||
                                                                            `(column ${i + 1})`}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </td>
                                                    <td className="max-w-48 truncate px-3 py-2 align-top text-gray-500">
                                                        {example}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {missingRequired.length > 0 && (
                                <p className="text-sm text-gray-600">
                                    * Required:{' '}
                                    {missingRequired
                                        .map((c) => c.name)
                                        .join(', ')}{' '}
                                    still needs a column.
                                </p>
                            )}
                            {fileError && (
                                <p className="text-sm text-red-600">
                                    {fileError}
                                </p>
                            )}

                            <div className="flex justify-end gap-3 pt-2">
                                <SecondaryButton onClick={reset}>
                                    Back
                                </SecondaryButton>
                                <PrimaryButton
                                    disabled={missingRequired.length > 0}
                                    onClick={() =>
                                        file && upload(file, mapping)
                                    }
                                >
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
                                <Alert variant="warning" role="status" className="max-h-48 overflow-y-auto p-3">
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
                                </Alert>
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
