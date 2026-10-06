import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button } from '@/Components/ui/button';
import { ReactNode, useEffect, useRef, useState } from 'react';

export const MAX_IMAGE_MB = 5;

/**
 * File picker for a single picture, with a live preview. `current` is the
 * URL already stored (if any); choosing a file replaces it, and "Remove"
 * asks the server to clear it. Wire `onChange`/`onRemove` into a multipart
 * form (see the `logo`/`remove_logo` fields on the customer form).
 */
export default function ImageField({
    label,
    preview,
    current,
    file,
    removed,
    onChange,
    onRemove,
    error,
    disabled,
    hint,
}: {
    label: string;
    /** Renders the picture (or its fallback) for a URL. */
    preview: (src: string | null) => ReactNode;
    current?: string | null;
    file: File | null;
    removed: boolean;
    onChange: (file: File | null) => void;
    onRemove: (removed: boolean) => void;
    error?: string;
    disabled?: boolean;
    hint?: string;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [objectUrl, setObjectUrl] = useState<string | null>(null);
    const [tooBig, setTooBig] = useState(false);

    useEffect(() => {
        if (!file) {
            setObjectUrl(null);
            return;
        }

        const url = URL.createObjectURL(file);
        setObjectUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const shown = objectUrl ?? (removed ? null : (current ?? null));
    const hasPicture = !!shown;

    const pick = (picked: File | null) => {
        if (picked && picked.size > MAX_IMAGE_MB * 1024 * 1024) {
            setTooBig(true);
            if (input.current) input.current.value = '';
            return;
        }

        setTooBig(false);
        onChange(picked);
        onRemove(false);
    };

    const clear = () => {
        if (input.current) input.current.value = '';
        setTooBig(false);
        onChange(null);
        onRemove(!!current);
    };

    return (
        <div>
            <InputLabel value={label} />
            <div className="mt-1 flex items-center gap-4">
                {preview(shown)}
                <div className="space-y-1">
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={disabled}
                            onClick={() => input.current?.click()}
                        >
                            {hasPicture ? 'Change' : 'Upload'}
                        </Button>
                        {hasPicture && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={disabled}
                                onClick={clear}
                            >
                                Remove
                            </Button>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {hint ??
                            `JPG, PNG, GIF or WebP, up to ${MAX_IMAGE_MB} MB.`}
                    </p>
                </div>
                <input
                    ref={input}
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    className="sr-only"
                    tabIndex={-1}
                    onChange={(e) => pick(e.target.files?.[0] ?? null)}
                />
            </div>
            {tooBig && (
                <InputError
                    className="mt-2"
                    message={`That file is larger than ${MAX_IMAGE_MB} MB.`}
                />
            )}
            <InputError className="mt-2" message={error} />
        </div>
    );
}
