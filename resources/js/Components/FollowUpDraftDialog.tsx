import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Alert } from '@/Components/ui/alert';
import axios from 'axios';
import { useEffect, useState } from 'react';

interface Draft {
    subject: string;
    body: string;
    to: string | null;
    /** Things to check before sending: a link, a stray address, hidden text. */
    warnings: string[];
}

/**
 * A drafted follow-up email for a quote that has gone quiet. Nothing is sent
 * from here: the owner reads and edits it, then copies it or opens it in
 * their own mail app. Drafts as soon as it opens, and can be redrafted
 * shorter.
 */
export default function FollowUpDraftDialog({
    quoteId,
    customer,
    onClose,
}: {
    quoteId: number;
    customer: string;
    onClose: () => void;
}) {
    const [busy, setBusy] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [draft, setDraft] = useState<Draft | null>(null);
    const [copied, setCopied] = useState(false);

    const generate = (tone: 'friendly' | 'brief') => {
        setBusy(true);
        setError(null);

        axios
            .post<Draft>(route('quotes.ai-follow-up', quoteId), { tone })
            .then(({ data }) => setDraft(data))
            .catch((e) =>
                setError(
                    e.response?.data?.message ??
                        'Something went wrong drafting that. Try again.',
                ),
            )
            .finally(() => setBusy(false));
    };

    // Draft once when the dialog opens.
    useEffect(() => {
        generate('friendly');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [quoteId]);

    const mailto = draft
        ? `mailto:${draft.to ?? ''}?subject=${encodeURIComponent(draft.subject)}&body=${encodeURIComponent(draft.body)}`
        : '#';

    const copy = () => {
        if (!draft) return;
        navigator.clipboard?.writeText(`${draft.subject}\n\n${draft.body}`);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    return (
        <Modal show onClose={onClose} maxWidth="lg">
            <div className="space-y-4 p-6">
                <div>
                    <h2 className="text-lg font-medium text-gray-900">
                        Follow up with {customer}
                    </h2>
                    <p className="mt-1 text-xs text-gray-500">
                        A draft to edit - nothing is sent from here. Check it
                        before you send it.
                    </p>
                </div>

                {error && (
                    <Alert variant="danger" role="alert">
                        {error}
                    </Alert>
                )}

                {busy && !draft && (
                    <p className="text-sm text-gray-500">Drafting…</p>
                )}

                {draft && draft.warnings.length > 0 && (
                    <Alert variant="warning" role="alert" className="text-sm">
                        <p className="font-medium">Check this before you send it</p>
                        <ul className="mt-1 list-disc ps-5">
                            {draft.warnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                            ))}
                        </ul>
                    </Alert>
                )}

                {draft && (
                    <div className="space-y-3">
                        {draft.to === null && draft.warnings.length === 0 && (
                            <p className="text-xs text-amber-700">
                                This customer has no email address on file.
                            </p>
                        )}
                        <div>
                            <InputLabel htmlFor="followup_subject" value="Subject" />
                            <input
                                id="followup_subject"
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                value={draft.subject}
                                onChange={(e) =>
                                    setDraft({ ...draft, subject: e.target.value })
                                }
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="followup_body" value="Message" />
                            <textarea
                                id="followup_body"
                                rows={9}
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                value={draft.body}
                                onChange={(e) =>
                                    setDraft({ ...draft, body: e.target.value })
                                }
                            />
                        </div>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    {draft && (
                        <>
                            <a
                                href={mailto}
                                className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                            >
                                Open in my email app
                            </a>
                            <SecondaryButton type="button" onClick={copy}>
                                {copied ? 'Copied' : 'Copy'}
                            </SecondaryButton>
                        </>
                    )}
                    <SecondaryButton
                        type="button"
                        disabled={busy}
                        onClick={() => generate('friendly')}
                    >
                        {draft ? 'Redraft' : 'Try again'}
                    </SecondaryButton>
                    {draft && (
                        <SecondaryButton
                            type="button"
                            disabled={busy}
                            onClick={() => generate('brief')}
                        >
                            Make it shorter
                        </SecondaryButton>
                    )}
                    <PrimaryButton
                        type="button"
                        className="ml-auto"
                        onClick={onClose}
                    >
                        Done
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    );
}
