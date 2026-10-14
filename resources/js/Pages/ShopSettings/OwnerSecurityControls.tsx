import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useShopDateTime } from '@/lib/datetime';
import { PageProps } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export interface OwnerControls {
    exports: {
        id: number;
        status: 'pending' | 'ready' | 'failed';
        size_bytes: number | null;
        created_at: string;
        expires_at: string | null;
        downloadable: boolean;
        error: string | null;
    }[];
    deletion: {
        scheduled_for: string | null;
        grace_days: number;
        shop_name: string;
    };
}

function Card({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <section className="mt-6 bg-white p-4 shadow-sm sm:rounded-lg sm:p-8">
            <h3 className="text-lg font-medium text-gray-900">{title}</h3>
            <p className="mt-1 max-w-2xl text-sm text-gray-500">
                {description}
            </p>
            <div className="mt-4">{children}</div>
        </section>
    );
}

const megabytes = (bytes: number | null) =>
    bytes === null ? '' : `${(bytes / 1_048_576).toFixed(1)} MB`;

/**
 * The owner-only part of Shop settings: getting the shop's data out or having
 * it erased (not on a self-hosted install - it is your server). The
 * two-factor requirement lives under Security.
 */
export default function OwnerSecurityControls({
    controls,
}: {
    controls: OwnerControls;
}) {
    const formatDate = useShopDateTime({ dateStyle: 'medium' });
    const { selfHosted } = usePage<PageProps>().props;
    const { exports, deletion } = controls;
    const deleteForm = useForm({ password: '', confirm_name: '' });
    const waiting = exports.some((e) => e.status === 'pending');

    // While an export is being built, look again every few seconds.
    useEffect(() => {
        if (!waiting) return;
        const timer = setInterval(
            () => router.reload({ only: ['ownerControls'] }),
            4000,
        );
        return () => clearInterval(timer);
    }, [waiting]);

    const submitDelete: FormEventHandler = (e) => {
        e.preventDefault();
        deleteForm.post(route('shop.data.deletion'), {
            preserveScroll: true,
            onSuccess: () => deleteForm.reset(),
            onError: () => deleteForm.reset('password'),
        });
    };

    return (
        <>
            <Card
                title="Export your data"
                description="Download everything this shop has stored - customers, quotes, jobs, catalog, team list and uploaded files - as a zip of spreadsheets (CSV) and files. Credentials such as your AI key are never included. We email you when it is ready; the file is kept for a few days."
            >
                <SecondaryButton
                    disabled={waiting}
                    onClick={() =>
                        router.post(
                            route('shop.data.export'),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    {waiting ? 'Preparing...' : 'Request export'}
                </SecondaryButton>

                {exports.length > 0 && (
                    <ul className="mt-4 divide-y text-sm">
                        {exports.map((e) => (
                            <li
                                key={e.id}
                                className="flex items-center justify-between py-2"
                            >
                                <span>
                                    {formatDate(e.created_at)}
                                    <span className="ms-2 text-gray-500">
                                        {e.status === 'pending' &&
                                            'preparing...'}
                                        {e.status === 'failed' && e.error}
                                        {e.status === 'ready' &&
                                            (e.downloadable
                                                ? `${megabytes(e.size_bytes)}, available until ${formatDate(e.expires_at!)}`
                                                : 'expired')}
                                    </span>
                                </span>
                                {e.downloadable && (
                                    <a
                                        href={route('shop.data.download', e.id)}
                                        className="font-medium text-indigo-600 hover:underline"
                                    >
                                        Download
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {/* Erasure is how a cloud shop leaves us; on your own server you own the data. */}
            {!selfHosted && (
                <Card
                    title="Delete this shop"
                    description={`Permanently erases the shop and all its data - customers, quotes, jobs, catalog and files. It is scheduled ${deletion.grace_days} days out, so you can export first or change your mind; until then the shop works as normal. Everyone in the shop is emailed. People's own accounts are not deleted. A record that the shop was deleted is kept.`}
                >
                    {deletion.scheduled_for ? (
                        <div className="space-y-3">
                            <p className="text-sm text-red-700">
                                Scheduled for permanent deletion on{' '}
                                {formatDate(deletion.scheduled_for)}.
                            </p>
                            <SecondaryButton
                                onClick={() =>
                                    router.delete(
                                        route('shop.data.deletion.cancel'),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Cancel deletion
                            </SecondaryButton>
                        </div>
                    ) : (
                        <form
                            onSubmit={submitDelete}
                            className="max-w-sm space-y-4"
                        >
                            <div>
                                <InputLabel
                                    htmlFor="confirm_name"
                                    value={`Type "${deletion.shop_name}" to confirm`}
                                />
                                <TextInput
                                    id="confirm_name"
                                    value={deleteForm.data.confirm_name}
                                    onChange={(e) =>
                                        deleteForm.setData(
                                            'confirm_name',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                    autoComplete="off"
                                />
                                <InputError
                                    message={deleteForm.errors.confirm_name}
                                    className="mt-2"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="delete_password"
                                    value="Your password"
                                />
                                <TextInput
                                    id="delete_password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={deleteForm.data.password}
                                    onChange={(e) =>
                                        deleteForm.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                                <InputError
                                    message={deleteForm.errors.password}
                                    className="mt-2"
                                />
                            </div>
                            <DangerButton disabled={deleteForm.processing}>
                                Schedule deletion
                            </DangerButton>
                        </form>
                    )}
                </Card>
            )}
        </>
    );
}
