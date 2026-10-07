import SubPageLinks from '@/Components/SubPageLinks';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Security() {
    return (
        <AuthenticatedLayout
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
                                title: 'Roles & permissions',
                                description:
                                    'What Admins and Staff can see and do in this shop.',
                                href: route('shop.permissions.edit'),
                            },
                            {
                                title: 'Audit log',
                                description:
                                    'Sign-ins and changes to access in your shop, and anything platform staff did to it.',
                                href: route('shop.security.log'),
                            },
                            {
                                title: 'Two-factor sign-in',
                                description:
                                    'Require an authenticator-app code at sign-in for the roles you choose, and how long they have to set it up.',
                                href: route('shop.security.policy'),
                            },
                            {
                                title: 'Data export and deletion',
                                description:
                                    'Download everything this shop has stored, or delete the shop. These live in Shop settings.',
                                href: route('shop.settings.edit'),
                            },
                        ]}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
