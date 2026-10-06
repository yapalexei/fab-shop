import { AiStatus } from '@/Components/AiQuoteDraftPanel';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Alert } from '@/Components/ui/alert';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';

interface DraftResponse<F> {
    /** False when the text didn't describe a single product. */
    found: boolean;
    fields: F;
    /** Things to double-check before saving. */
    checks: string[];
}

/**
 * "Fill from a supplier page": paste the product page's text and get the
 * form's fields suggested. Nothing is fetched or saved from here - the
 * values go into the form below for review, and anything the page didn't
 * state stays as it was.
 */
export default function AiCatalogDraftPanel<F extends object>({
    kind,
    noun,
    ai,
    withLink = false,
    onDraft,
}: {
    kind: 'tools' | 'materials' | 'supplier-prices';
    /** "tool" / "material" / "price", for the copy. */
    noun: string;
    ai: AiStatus;
    /** Offer a link box that is handed back untouched (the AI never sees it). */
    withLink?: boolean;
    /** Fill the form; return false when nothing could be applied. */
    onDraft: (fields: F) => boolean | void;
}) {
    const { auth } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const [text, setText] = useState('');
    const [url, setUrl] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<{
        found: boolean;
        applied: boolean;
        checks: string[];
    } | null>(null);

    // Not entitled: offer nothing, rather than a button that can only fail.
    if (!ai.available) return null;

    if (!ai.configured) {
        return (
            <p className="mb-4 text-sm text-muted-foreground">
                Want to fill this in from a supplier&apos;s page?{' '}
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
            .post<DraftResponse<F>>(route('catalog.ai-draft', kind), {
                text,
                source_url: url.trim() || null,
            })
            .then(({ data }) => {
                const applied = data.found && onDraft(data.fields) !== false;
                setResult({
                    found: data.found,
                    applied,
                    checks: data.checks,
                });
                if (applied) setText('');
            })
            .catch((e) => {
                const errors = e.response?.data?.errors;

                setError(
                    (errors && (Object.values(errors).flat()[0] as string)) ??
                        e.response?.data?.message ??
                        'Something went wrong reading that. Try again.',
                );
            })
            .finally(() => setBusy(false));
    };

    return (
        <div className="mb-6 rounded-md border bg-card p-4">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium">
                        Fill from a supplier page
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Copy the product details from the supplier&apos;s site
                        and paste them here to fill in this {noun}. Nothing is
                        saved until you do.
                    </p>
                </div>
                {!open && (
                    <SecondaryButton type="button" onClick={() => setOpen(true)}>
                        Fill with AI
                    </SecondaryButton>
                )}
            </div>

            {open && (
                <div className="mt-3 space-y-3">
                    <textarea
                        aria-label="Supplier page text"
                        className="block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                        rows={6}
                        placeholder="Paste the product page text here…"
                        value={text}
                        onChange={(e) => setText(e.target.value)}
                        disabled={busy}
                    />

                    {withLink && (
                        <div>
                            <InputLabel
                                htmlFor="ai-source-url"
                                value="Product page link (optional)"
                            />
                            <TextInput
                                id="ai-source-url"
                                type="url"
                                className="mt-1 block w-full"
                                placeholder="https://"
                                value={url}
                                onChange={(e) => setUrl(e.target.value)}
                                // This panel can sit inside a form: Enter
                                // here must not save it.
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') e.preventDefault();
                                }}
                                disabled={busy}
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Saved so you can open the page later to check
                                the price. The AI doesn&apos;t visit it.
                            </p>
                        </div>
                    )}

                    {error && (
                        <Alert variant="danger" role="alert">
                            {error}
                        </Alert>
                    )}

                    {result && (
                        <div className="space-y-1 text-sm" role="status">
                            <p>
                                {result.applied
                                    ? 'Filled in what the page stated - review the form.'
                                    : result.found
                                      ? 'Read the product, but nothing could be added - see below.'
                                      : "That didn't look like a single product, so nothing was filled in."}
                            </p>
                            {result.checks.length > 0 && (
                                <ul className="list-disc pl-5 text-muted-foreground">
                                    {result.checks.map((check) => (
                                        <li key={check}>{check}</li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-2">
                        <PrimaryButton
                            type="button"
                            onClick={draft}
                            disabled={busy || text.trim().length < 10}
                        >
                            {busy ? 'Reading…' : 'Fill in the form'}
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
