import SecurityLogTable, {
    SecurityLogPage,
} from '@/Components/SecurityLogTable';
import { BackLink } from '@/Components/SubPageLinks';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function SecurityLog({
    log,
    events,
    filters,
}: PageProps<{
    log: SecurityLogPage;
    events: string[];
    filters: { event?: string; q?: string };
}>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Audit log
                </h2>
            }
        >
            <Head title="Audit log" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <BackLink
                        href={route('shop.security.index')}
                        label="Security"
                    />
                    <p className="text-sm text-gray-600">
                        Sign-ins and changes to access in your shop, and
                        anything platform staff did to it.
                    </p>
                    <SecurityLogTable
                        log={log}
                        events={events}
                        filters={filters}
                        url={route('shop.security.log')}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
