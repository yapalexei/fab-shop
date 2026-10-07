import SecurityLogTable, {
    SecurityLogPage,
} from '@/Components/SecurityLogTable';
import { BackLink } from '@/Components/SubPageLinks';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
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
        <PlatformAdminLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Security log
                </h2>
            }
        >
            <Head title="Security log" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    <BackLink
                        href={route('platform-admin.security.index')}
                        label="Security"
                    />
                    <SecurityLogTable
                        log={log}
                        events={events}
                        filters={filters}
                        url={route('platform-admin.security.log')}
                    />
                </div>
            </div>
        </PlatformAdminLayout>
    );
}
