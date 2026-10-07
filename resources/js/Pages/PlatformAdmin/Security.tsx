import SubPageLinks from '@/Components/SubPageLinks';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
import { Head } from '@inertiajs/react';

export default function Security() {
    return (
        <PlatformAdminLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Security
                </h2>
            }
        >
            <Head title="Security" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <SubPageLinks
                        pages={[
                            {
                                title: 'Two-factor requirement',
                                description:
                                    'The default for every shop: which roles must use two-factor sign-in, and how long they have to set it up.',
                                href: route('platform-admin.security.policy'),
                            },
                            {
                                title: 'Security log',
                                description:
                                    'Sign-ins, failures and changes to access across all shops.',
                                href: route('platform-admin.security.log'),
                            },
                        ]}
                    />
                </div>
            </div>
        </PlatformAdminLayout>
    );
}
