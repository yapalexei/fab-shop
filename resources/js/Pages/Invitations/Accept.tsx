import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import LegalLinks from '@/Components/LegalLinks';
import TermsAgreement from '@/Components/TermsAgreement';
import TextInput from '@/Components/TextInput';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

type Mode = 'accepted' | 'expired' | 'ready' | 'login-required' | 'create-account';

function Shell({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0">
            <Link href="/" className="flex items-center gap-2 text-gray-900">
                <ApplicationLogo className="h-10 w-10 fill-current text-gray-500" />
            </Link>
            <Card className="mt-6 w-full overflow-hidden px-6 py-4 sm:max-w-md">
                {children}
            </Card>
            <LegalLinks className="my-6" />
        </div>
    );
}

export default function Accept({
    mode,
    token,
    email,
    role,
    shopName,
    agreementText,
}: PageProps<{
    mode: Mode;
    token?: string;
    email?: string;
    role?: string;
    shopName: string;
    agreementText?: string;
}>) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        password: '',
        password_confirmation: '',
        accepted_terms: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('invitations.accept', token));
    };

    if (mode === 'accepted') {
        return (
            <Shell>
                <Head title="Invitation already accepted" />
                <p className="text-sm text-gray-600">
                    This invitation to join <strong>{shopName}</strong> has
                    already been accepted.
                </p>
                <Link
                    href={route('login')}
                    className="mt-4 inline-block text-sm text-indigo-600 hover:underline"
                >
                    Log in
                </Link>
            </Shell>
        );
    }

    if (mode === 'expired') {
        return (
            <Shell>
                <Head title="Invitation expired" />
                <p className="text-sm text-gray-600">
                    This invitation to join <strong>{shopName}</strong> has
                    expired. Ask the shop owner to send you a new one.
                </p>
            </Shell>
        );
    }

    return (
        <Shell>
            <Head title={`Join ${shopName}`} />

            <h1 className="text-lg font-medium text-gray-900">
                Join {shopName}
            </h1>
            <p className="mt-1 text-sm text-gray-600">
                You've been invited to join as <strong>{role}</strong>
                {email && (
                    <>
                        {' '}
                        ({email})
                    </>
                )}
                .
            </p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                {mode === 'create-account' && (
                    <>
                        <div>
                            <InputLabel htmlFor="name" value="Your name" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                isFocused
                                required
                            />
                            <InputError
                                message={errors.name}
                                className="mt-2"
                            />
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="password"
                                value="Choose a password"
                            />
                            <TextInput
                                id="password"
                                type="password"
                                className="mt-1 block w-full"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                required
                            />
                            <InputError
                                message={errors.password}
                                className="mt-2"
                            />
                        </div>
                        <div>
                            <InputLabel
                                htmlFor="password_confirmation"
                                value="Confirm password"
                            />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                className="mt-1 block w-full"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                    </>
                )}

                {mode === 'login-required' && (
                    <div>
                        <InputLabel
                            htmlFor="password"
                            value={`Password for ${email}`}
                        />
                        <TextInput
                            id="password"
                            type="password"
                            className="mt-1 block w-full"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            isFocused
                            required
                        />
                        <InputError message={errors.password} className="mt-2" />
                        <p className="mt-2 text-xs text-gray-500">
                            An account already exists for this email - log in
                            to accept.
                        </p>
                    </div>
                )}

                {mode === 'ready' && (
                    <p className="text-sm text-gray-600">
                        You're signed in as {email} - ready to join.
                    </p>
                )}

                {mode === 'create-account' && agreementText && (
                    <TermsAgreement
                        checked={data.accepted_terms}
                        onChange={(checked) =>
                            setData('accepted_terms', checked)
                        }
                        text={agreementText}
                        error={errors.accepted_terms}
                    />
                )}

                <PrimaryButton disabled={processing}>
                    {mode === 'create-account'
                        ? 'Create account & join'
                        : 'Accept invitation'}
                </PrimaryButton>
            </form>
        </Shell>
    );
}
