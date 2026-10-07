import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import ProfilePictureForm from './Partials/ProfilePictureForm';
import DeleteUserForm from './Partials/DeleteUserForm';
import TwoFactorForm, { TwoFactorProps } from './Partials/TwoFactorForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Card } from '@/Components/ui/card';

export default function Edit({
    mustVerifyEmail,
    status,
    twoFactor,
}: PageProps<{
    mustVerifyEmail: boolean;
    status?: string;
    twoFactor: TwoFactorProps;
}>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Profile
                </h2>
            }
        >
            <Head title="Profile" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <Card className="p-4 sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </Card>

                    <Card className="p-4 sm:p-8">
                        <ProfilePictureForm className="max-w-xl" />
                    </Card>

                    <Card className="p-4 sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </Card>

                    <Card className="p-4 sm:p-8">
                        <TwoFactorForm
                            twoFactor={twoFactor}
                            className="max-w-xl"
                        />
                    </Card>

                    <Card className="p-4 sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
