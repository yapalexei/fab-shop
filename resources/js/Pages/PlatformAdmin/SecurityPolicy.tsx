import PageHelp from '@/Components/PageHelp';
import { BackLink } from '@/Components/SubPageLinks';
import TwoFactorPolicyForm, {
    PolicyValues,
} from '@/Components/TwoFactorPolicyForm';
import { Card } from '@/Components/ui/card';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function SecurityPolicy({
    policy,
    roleOptions,
    maxGraceDays,
}: PageProps<{
    policy: PolicyValues & { set_at: string | null };
    roleOptions: string[];
    maxGraceDays: number;
}>) {
    return (
        <PlatformAdminLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Two-factor requirement
                </h2>
            }
        >
            <Head title="Two-factor requirement" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <BackLink
                        href={route('platform-admin.security.index')}
                        label="Security"
                    />
                    <Card className="p-4 sm:p-8">
                        <h3 className="text-lg font-medium text-gray-900">
                            Default for all shops
                        </h3>
                        <PageHelp>
                            Two-factor stays optional for anyone this does not
                            cover. A shop&apos;s owner can follow this default
                            or set their own requirement, including none.
                        </PageHelp>
                        <div className="mt-4 max-w-xl">
                            <TwoFactorPolicyForm
                                action={route(
                                    'platform-admin.security.two-factor',
                                )}
                                roleOptions={roleOptions}
                                maxGraceDays={maxGraceDays}
                                values={policy}
                            />
                        </div>
                    </Card>
                </div>
            </div>
        </PlatformAdminLayout>
    );
}
