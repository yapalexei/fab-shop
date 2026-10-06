import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Alert } from '@/Components/ui/alert';
import { AiDraftResult } from '@/lib/aiQuoteDraft';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';

export interface AiStatus {
    /** Entitled right now (trial or plan). */
    available: boolean;
    /** A provider key is saved. */
    configured: boolean;
}

/**
 * "Draft from a customer email": paste the request, get suggested lines
 * appended to the quote below for review. Nothing is saved from here - the
 * lines go into the form like any others, flagged where the AI wasn't sure.
 */
export default function AiQuoteDraftPanel({
    ai,
    onDraft,
}: {
    ai: AiStatus;
    /** Receives the suggestions; returns how many lines it added. */
    onDraft: (result: AiDraftResult) => number;
}) {
    const { auth } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<{
        added: number;
        questions: string[];
        truncated: boolean;
    } | null>(null);

    // Not entitled (trial over, plan without it): offer nothing, rather than
    // a button that can only fail.
    if (!ai.available) return null;

    if (!ai.configured) {
        return (
            <p className="mb-4 text-sm text-muted-foreground">
                Want to draft quotes from a customer&apos;s email?{' '}
                {auth.isOwner ? (
                    <Link
                        href={route('shop.ai.edit')}
                        className="font-medium underline"
                    >
                        Connect your AI provider
                    </Link>
                ) : (
                    'Ask the shop owner to connect an AI provider in Settings.'
                )}
            </p>
        );
    }

    const draft = () => {
        setBusy(true);
        setError(null);
        setResult(null);

        axios
            .post<AiDraftResult>(route('quotes.ai-draft'), { message })
            .then(({ data }) => {
                const added = onDraft(data);
                setResult({
                    added,
                    questions: data.questions,
                    truncated: data.catalog_truncated,
                });
                if (added > 0) setMessage('');
            })
            .catch((e) => {
                setError(
                    e.response?.data?.errors?.message?.[0] ??
                        e.response?.data?.message ??
                        'Something went wrong drafting that. Try again.',
                );
            })
            .finally(() => setBusy(false));
    };

    return (
        <div className="mb-4 rounded-md border bg-card p-4">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium">
                        Draft from a customer email
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Paste their request and get suggested line items to
                        review. Costs come from your catalog, never from the
                        AI.
                    </p>
                </div>
                {!open && (
                    <SecondaryButton type="button" onClick={() => setOpen(true)}>
                        Draft with AI
                    </SecondaryButton>
                )}
            </div>

            {open && (
                <div className="mt-3 space-y-3">
                    <textarea
                        aria-label="Customer request"
                        className="block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                        rows={6}
                        placeholder="Paste the customer's email or request here…"
                        value={message}
                        onChange={(e) => setMessage(e.target.value)}
                        disabled={busy}
                    />

                    {error && (
                        <Alert variant="danger" role="alert">
                            {error}
                        </Alert>
                    )}

                    {result && (
                        <div className="space-y-2 text-sm" role="status">
                            <p>
                                {result.added > 0
                                    ? `Added ${result.added} suggested line${result.added === 1 ? '' : 's'} below - check each one before saving.`
                                    : "That didn't look like a request for work, so nothing was added."}
                            </p>
                            {result.truncated && (
                                <p className="text-muted-foreground">
                                    Your catalog is large, so only part of it
                                    was considered. Some lines may be missing
                                    a match.
                                </p>
                            )}
                            {result.questions.length > 0 && (
                                <div>
                                    <p className="font-medium">
                                        Worth asking the customer:
                                    </p>
                                    <ul className="list-disc pl-5">
                                        {result.questions.map((q) => (
                                            <li key={q}>{q}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-2">
                        <PrimaryButton
                            type="button"
                            onClick={draft}
                            disabled={busy || message.trim().length < 10}
                        >
                            {busy ? 'Drafting…' : 'Draft line items'}
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            onClick={() => setOpen(false)}
                            disabled={busy}
                        >
                            Close
                        </SecondaryButton>
                    </div>
                </div>
            )}
        </div>
    );
}
