import { UserAvatar } from '@/Components/Avatar';
import { MAX_IMAGE_MB } from '@/Components/ImageField';
import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ChangeEvent, useRef, useState } from 'react';

export default function ProfilePictureForm({
    className = '',
}: {
    className?: string;
}) {
    const user = usePage<PageProps>().props.auth.user;
    const input = useRef<HTMLInputElement>(null);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const upload = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        e.target.value = '';

        if (!file) return;

        if (file.size > MAX_IMAGE_MB * 1024 * 1024) {
            setError(`That file is larger than ${MAX_IMAGE_MB} MB.`);
            return;
        }

        setError(null);
        router.post(
            route('profile.avatar.store'),
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) => setError(errors.avatar ?? null),
            },
        );
    };

    const remove = () => {
        router.delete(route('profile.avatar.destroy'), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Profile picture
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Shown next to your name on jobs you're assigned to and
                    across your shop. It's cropped to a square, then shown in
                    a circle.
                </p>
            </header>

            <div className="mt-6 flex items-center gap-4">
                <UserAvatar
                    name={user.name}
                    src={user.avatar_url}
                    className="size-20 text-2xl"
                />
                <div className="space-y-1">
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={processing}
                            onClick={() => input.current?.click()}
                        >
                            {user.avatar_url ? 'Change' : 'Upload'}
                        </Button>
                        {user.avatar_url && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={processing}
                                onClick={remove}
                            >
                                Remove
                            </Button>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        JPG, PNG, GIF or WebP, up to {MAX_IMAGE_MB} MB.
                    </p>
                </div>
                <input
                    ref={input}
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    className="sr-only"
                    tabIndex={-1}
                    onChange={upload}
                />
            </div>

            <InputError className="mt-2" message={error ?? undefined} />
        </section>
    );
}
