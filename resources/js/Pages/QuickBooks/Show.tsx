import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useShopDateTime } from '@/lib/datetime';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';

interface Connection {
    realm_id: string;
    connected_at: string;
}

export default function Show({
    connection,
}: PageProps<{ connection: Connection | null }>) {
    const formatDateTime = useShopDateTime();

    const disconnect = () => {
        if (
            !confirm(
                'Disconnect QuickBooks? Jobs will stop syncing invoices until you reconnect.',
            )
        ) {
            return;
        }

        router.delete(route('quickbooks.destroy'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    QuickBooks
                </h2>
            }
        >
            <Head title="QuickBooks" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg">
                        {connection ? (
                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <span className="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">
                                        Connected
                                    </span>
                                    <span className="text-sm text-gray-500">
                                        Company ID {connection.realm_id}
                                    </span>
                                </div>
                                <p className="text-sm text-gray-600">
                                    Connected{' '}
                                    {formatDateTime(connection.connected_at)}
                                    . Jobs automatically push an invoice to
                                    QuickBooks when they're marked Invoiced,
                                    reusing a matching customer if one
                                    already exists there instead of creating
                                    a duplicate.
                                </p>
                                <SecondaryButton onClick={disconnect}>
                                    Disconnect
                                </SecondaryButton>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <p className="text-sm text-gray-600">
                                    Connect QuickBooks Online to
                                    automatically push an invoice when a job
                                    is marked Invoiced - customers are
                                    matched by email instead of duplicated.
                                </p>
                                <a href={route('quickbooks.connect')}>
                                    <PrimaryButton>
                                        Connect QuickBooks
                                    </PrimaryButton>
                                </a>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
