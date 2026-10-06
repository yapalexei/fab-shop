import { Attachment, Row } from '@/Components/Attachments';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

export const MAX_DOCUMENTS = 3;
export const MAX_DOCUMENT_MB = 30;

const ACCEPT =
    'application/pdf,image/jpeg,image/png,image/gif,image/webp,.pdf,.jpg,.jpeg,.png,.gif,.webp';
const ALLOWED = /\.(pdf|jpe?g|png|gif|webp)$/i;

/**
 * The scan or photo of the certificate itself. On a saved certification files
 * upload and delete straight away; on the new-certification form there is
 * nothing to attach to yet, so files are held in `staged` and sent along
 * with the record when it is saved.
 */
export default function CertificationDocuments({
    certificationId,
    documents = [],
    staged = [],
    onStage,
    error,
    readOnly = false,
}: {
    certificationId?: number;
    documents?: Attachment[];
    staged?: File[];
    onStage?: (files: File[]) => void;
    error?: string;
    readOnly?: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [problem, setProblem] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);

    const count = documents.length + staged.length;
    const room = MAX_DOCUMENTS - count;

    const pick = (list: FileList | null) => {
        const files = Array.from(list ?? []);
        if (input.current) input.current.value = '';
        if (files.length === 0) return;

        const wrongType = files.filter((f) => !ALLOWED.test(f.name));
        if (wrongType.length > 0) {
            setProblem(
                `${wrongType.map((f) => f.name).join(', ')} isn't a PDF or an image.`,
            );
            return;
        }

        const tooBig = files.filter(
            (f) => f.size > MAX_DOCUMENT_MB * 1024 * 1024,
        );
        if (tooBig.length > 0) {
            setProblem(
                `${tooBig.map((f) => f.name).join(', ')} is over the ${MAX_DOCUMENT_MB} MB limit.`,
            );
            return;
        }

        if (files.length > room) {
            setProblem(
                `A certification can have up to ${MAX_DOCUMENTS} documents - you can add ${room} more.`,
            );
            return;
        }

        setProblem(null);

        if (certificationId === undefined) {
            onStage?.([...staged, ...files]);
            return;
        }

        const data = new FormData();
        files.forEach((file) => data.append('documents[]', file));
        router.post(
            route('certifications.documents.store', certificationId),
            data,
            {
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
            },
        );
    };

    const destroy = (document: Attachment) => {
        if (!confirm(`Delete ${document.name}?`)) return;
        router.delete(
            route('certifications.documents.destroy', [
                certificationId,
                document.id,
            ]),
            { preserveScroll: true },
        );
    };

    return (
        <div className="mt-4">
            <InputLabel htmlFor="documents" value="Documents" />
            <p className="mt-1 text-xs text-muted-foreground">
                A scan or photo of the certificate, so the record has something
                to point to. PDF or image (JPG, PNG, GIF, WebP), up to{' '}
                {MAX_DOCUMENT_MB} MB each, {MAX_DOCUMENTS} files per
                certification.
            </p>

            {count > 0 && (
                <ul className="mt-2 space-y-2">
                    {documents.map((d) => (
                        <Row
                            key={d.id}
                            name={d.name}
                            size={d.size}
                            href={d.file_path}
                            thumbnail={d.thumbnail}
                            kind={d.kind}
                            onRemove={readOnly ? undefined : () => destroy(d)}
                        />
                    ))}
                    {staged.map((file, i) => (
                        <Row
                            key={`${file.name}-${i}`}
                            name={file.name}
                            size={file.size}
                            onRemove={() =>
                                onStage?.(staged.filter((_, j) => j !== i))
                            }
                        />
                    ))}
                </ul>
            )}

            {!readOnly && room > 0 && (
                <input
                    id="documents"
                    ref={input}
                    type="file"
                    multiple
                    accept={ACCEPT}
                    disabled={uploading}
                    className="mt-2 block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-xs file:font-semibold hover:file:bg-accent"
                    onChange={(e) => pick(e.target.files)}
                />
            )}
            {!readOnly && room === 0 && (
                <p className="mt-2 text-xs text-muted-foreground">
                    The limit of {MAX_DOCUMENTS} documents is reached - remove
                    one to add another.
                </p>
            )}
            <InputError message={problem ?? error} className="mt-2" />
        </div>
    );
}
