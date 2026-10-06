import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { router } from '@inertiajs/react';
import { Box, File, Loader2, FileText, Image as ImageIcon, Ruler } from 'lucide-react';
import { Suspense, lazy, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

// three.js is large and only needed once someone opens a model.
const loadModelViewer = () => import('@/Components/ModelViewer');
const ModelViewer = lazy(loadModelViewer);
const VIEWABLE_3D = ['stl', 'obj', '3mf'];

export interface Attachment {
    id: number;
    name: string;
    size: number;
    mime_type: string;
    /** 'image' | 'pdf' | 'step' | 'stl' | '3mf' | 'obj' | 'dxf' | 'dwg' | 'file' */
    kind: string;
    /** Full-size file / download. */
    file_path: string;
    /** Square preview image, or null when the client should draw an icon. */
    thumbnail: string | null;
}

const KIND_ICONS: Record<string, typeof File> = {
    image: ImageIcon,
    pdf: FileText,
    step: Box,
    stl: Box,
    '3mf': Box,
    obj: Box,
    dxf: Ruler,
    dwg: Ruler,
};

/** Square, masked thumbnail - or a generic icon for the file's kind. */
function Thumb({
    thumbnail,
    kind,
}: {
    thumbnail?: string | null;
    kind?: string;
}) {
    const Icon = KIND_ICONS[kind ?? ''] ?? File;

    return (
        <span className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted text-muted-foreground">
            {thumbnail ? (
                <img
                    src={thumbnail}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
            ) : (
                <Icon className="size-6" aria-hidden />
            )}
        </span>
    );
}

export const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024;

export function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function ModelDialog({
    name,
    kind,
    href,
    onClose,
}: {
    name: string;
    kind: string;
    href: string;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="truncate">{name}</DialogTitle>
                    <DialogDescription>
                        Drag to rotate, scroll to zoom, right-drag to pan.{' '}
                        <a href={href} download={name} className="underline">
                            Download
                        </a>
                    </DialogDescription>
                </DialogHeader>
                <Suspense
                    fallback={
                        <div
                            role="status"
                            className="flex h-[60vh] items-center justify-center gap-2 text-sm text-muted-foreground"
                        >
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden
                            />
                            Loading 3D viewer…
                        </div>
                    }
                >
                    <ModelViewer kind={kind} url={href} />
                </Suspense>
            </DialogContent>
        </Dialog>
    );
}

export function Row({
    name,
    size,
    href,
    thumbnail,
    kind,
    onRemove,
}: {
    name: string;
    size: number;
    href?: string;
    thumbnail?: string | null;
    kind?: string;
    onRemove?: () => void;
}) {
    const [viewing, setViewing] = useState(false);
    const viewable = !!href && VIEWABLE_3D.includes(kind ?? '');

    return (
        <li className="flex items-center gap-3 rounded-md border px-3 py-2 text-sm">
            <Thumb thumbnail={thumbnail} kind={kind} />
            <span className="min-w-0 flex-1 truncate">
                {viewable ? (
                    <button
                        type="button"
                        // Start downloading the viewer as soon as someone
                        // shows intent, so it's usually ready by the click.
                        // The dialog shows a loading state if it isn't.
                        onPointerEnter={loadModelViewer}
                        onFocus={loadModelViewer}
                        onClick={() => setViewing(true)}
                        className="font-medium underline"
                    >
                        {name}
                    </button>
                ) : href ? (
                    <a
                        href={href}
                        target="_blank"
                        rel="noreferrer"
                        className="font-medium underline"
                    >
                        {name}
                    </a>
                ) : (
                    name
                )}
                {viewing && href && (
                    <ModelDialog
                        name={name}
                        kind={kind ?? ''}
                        href={href}
                        onClose={() => setViewing(false)}
                    />
                )}
                <span className="ml-2 text-xs text-muted-foreground">
                    {formatSize(size)}
                </span>
            </span>
            {onRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="text-xs text-red-600 hover:underline"
                >
                    Remove
                </button>
            )}
        </li>
    );
}

/**
 * Files on a quote. On a saved quote they upload and delete straight away; on
 * the new-quote form there's nothing to attach to yet, so files are held in
 * `staged` and sent along with the quote when it's saved.
 */
export function QuoteAttachments({
    quoteId,
    attachments = [],
    staged,
    onStage,
    error,
}: {
    quoteId?: number;
    attachments?: Attachment[];
    staged?: File[];
    onStage?: (files: File[]) => void;
    error?: string;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [sizeError, setSizeError] = useState<string | null>(null);

    const pick = (list: FileList | null) => {
        const files = Array.from(list ?? []);
        if (fileInput.current) fileInput.current.value = '';
        if (files.length === 0) return;

        const tooBig = files.filter((f) => f.size > MAX_ATTACHMENT_BYTES);
        if (tooBig.length > 0) {
            setSizeError(
                `${tooBig.map((f) => f.name).join(', ')} is over the 20 MB limit.`,
            );
            return;
        }
        setSizeError(null);

        if (quoteId === undefined) {
            onStage?.([...(staged ?? []), ...files]);
            return;
        }

        const data = new FormData();
        files.forEach((file) => data.append('attachments[]', file));
        router.post(route('quotes.attachments.store', quoteId), data, {
            preserveScroll: true,
        });
    };

    const destroy = (attachment: Attachment) => {
        if (!confirm(`Delete ${attachment.name}?`)) return;
        router.delete(
            route('quotes.attachments.destroy', [quoteId, attachment.id]),
            { preserveScroll: true },
        );
    };

    const empty = attachments.length === 0 && (staged ?? []).length === 0;

    return (
        <div className="mt-4">
            <InputLabel htmlFor="attachments" value="Attachments" />
            <p className="mt-1 text-xs text-muted-foreground">
                Blueprints, photos or other files for this quote - carried over
                to the job if it's converted. Up to 20 MB each.
            </p>

            {!empty && (
                <ul className="mt-2 space-y-2">
                    {attachments.map((a) => (
                        <Row
                            key={a.id}
                            name={a.name}
                            size={a.size}
                            href={a.file_path}
                            thumbnail={a.thumbnail}
                            kind={a.kind}
                            onRemove={() => destroy(a)}
                        />
                    ))}
                    {(staged ?? []).map((file, i) => (
                        <Row
                            key={`${file.name}-${i}`}
                            name={file.name}
                            size={file.size}
                            onRemove={() =>
                                onStage?.((staged ?? []).filter((_, j) => j !== i))
                            }
                        />
                    ))}
                </ul>
            )}

            <input
                id="attachments"
                ref={fileInput}
                type="file"
                multiple
                className="mt-2 block w-full text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-xs file:font-semibold hover:file:bg-accent"
                onChange={(e) => pick(e.target.files)}
            />
            <InputError message={sizeError ?? error} className="mt-2" />
        </div>
    );
}

/** The job's copy of the quote's files - view/download only. */
export function JobAttachments({ attachments }: { attachments: Attachment[] }) {
    if (attachments.length === 0) return null;

    return (
        <div className="rounded-lg border bg-card p-6">
            <h3 className="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">
                Attachments
            </h3>
            <ul className="space-y-2">
                {attachments.map((a) => (
                    <Row
                        key={a.id}
                        name={a.name}
                        size={a.size}
                        href={a.file_path}
                        thumbnail={a.thumbnail}
                        kind={a.kind}
                    />
                ))}
            </ul>
        </div>
    );
}
