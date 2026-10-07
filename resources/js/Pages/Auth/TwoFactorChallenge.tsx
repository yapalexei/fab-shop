import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        recovery_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('two-factor.login'), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    const switchMode = () => {
        setUseRecovery((v) => !v);
        reset('code', 'recovery_code');
    };

    return (
        <GuestLayout>
            <Head title="Two-factor sign-in" />

            <p className="mb-4 text-sm text-gray-600">
                {useRecovery
                    ? 'Enter one of the recovery codes you saved when you turned on two-factor sign-in. Each code works once.'
                    : 'Enter the 6-digit code from your authenticator app.'}
            </p>

            <form onSubmit={submit}>
                {useRecovery ? (
                    <div>
                        <InputLabel
                            htmlFor="recovery_code"
                            value="Recovery code"
                        />
                        <TextInput
                            id="recovery_code"
                            value={data.recovery_code}
                            className="mt-1 block w-full"
                            autoComplete="one-time-code"
                            isFocused={true}
                            onChange={(e) =>
                                setData('recovery_code', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.recovery_code}
                            className="mt-2"
                        />
                    </div>
                ) : (
                    <div>
                        <InputLabel
                            htmlFor="code"
                            value="Authentication code"
                        />
                        <TextInput
                            id="code"
                            inputMode="numeric"
                            value={data.code}
                            className="mt-1 block w-full"
                            autoComplete="one-time-code"
                            isFocused={true}
                            onChange={(e) => setData('code', e.target.value)}
                        />
                        <InputError message={errors.code} className="mt-2" />
                    </div>
                )}

                <div className="mt-4 flex items-center justify-between">
                    <button
                        type="button"
                        onClick={switchMode}
                        className="text-sm text-gray-600 underline hover:text-foreground"
                    >
                        {useRecovery
                            ? 'Use an authenticator code'
                            : 'Use a recovery code'}
                    </button>

                    <PrimaryButton disabled={processing}>
                        Continue
                    </PrimaryButton>
                </div>

                <div className="mt-4 text-center">
                    <Link
                        href={route('login')}
                        className="text-sm text-gray-600 underline hover:text-foreground"
                    >
                        Back to log in
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
