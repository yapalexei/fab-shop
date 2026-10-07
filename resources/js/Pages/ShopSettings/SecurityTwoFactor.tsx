import { BackLink } from '@/Components/SubPageLinks';
import TwoFactorPolicyForm, {
    PolicyValues,
} from '@/Components/TwoFactorPolicyForm';
import { Card } from '@/Components/ui/card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function SecurityTwoFactor({
    twoFactor,
}: PageProps<{
    twoFactor: {
        follows_default: boolean;
        values: PolicyValues;
        platform_default: PolicyValues;
        role_options: string[];
        max_grace_days: number;
    };
}>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Two-factor sign-in
                </h2>
            }
        >
            <Head title="Two-factor sign-in" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <BackLink
                        href={route('shop.security.index')}
                        label="Security"
                    />
                    <Card className="p-4 sm:p-8">
                        <p className="max-w-2xl text-sm text-gray-500">
                            Require a code from an authenticator app, as well as
                            a password, for the roles you choose. It stays
                            optional for everyone else. People are emailed when
                            a requirement applies to them and get a reminder in
                            the app until they set it up.
                        </p>
                        <div className="mt-4 max-w-xl">
                            <TwoFactorPolicyForm
                                action={route('shop.security.two-factor')}
                                roleOptions={twoFactor.role_options}
                                maxGraceDays={twoFactor.max_grace_days}
                                values={twoFactor.values}
                                platformDefault={twoFactor.platform_default}
                                followsDefault={twoFactor.follows_default}
                            />
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
