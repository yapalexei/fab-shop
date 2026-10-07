import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import DangerButton from '@/Components/DangerButton';
import { useForm, router } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { FormEventHandler, useState } from 'react';

export type TwoFactorProps = {
    enabled: boolean;
    setup: { secret: string; uri: string } | null;
    recovery_codes_left: number;
    new_recovery_codes: string[] | null;
};

export default function TwoFactorForm({
    twoFactor,
    className = '',
}: {
    twoFactor: TwoFactorProps;
    className?: string;
}) {
    const confirm = useForm({ code: '' });
    const passwordForm = useForm({ password: '' });
    // Which password-protected action the password box is currently for.
    const [asking, setAsking] = useState<'disable' | 'codes' | null>(null);

    const submitConfirm: FormEventHandler = (e) => {
        e.preventDefault();
        confirm.post(route('two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirm.reset(),
        });
    };

    const submitPassword: FormEventHandler = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset();
                setAsking(null);
            },
            onError: () => passwordForm.reset('password'),
        };

        if (asking === 'disable') {
            passwordForm.delete(route('two-factor.disable'), options);
        } else {
            passwordForm.post(route('two-factor.recovery-codes'), options);
        }
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Two-factor sign-in
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    Ask for a code from an authenticator app (1Password, Authy,
                    Google Authenticator...) after your password. Even if your
                    password leaks, nobody can sign in without your phone.
                </p>
            </header>

            {twoFactor.new_recovery_codes && (
                <div className="mt-6 rounded-md border border-warning/40 bg-warning/10 p-4">
                    <p className="text-sm font-medium text-gray-900">
                        Save these recovery codes somewhere safe.
                    </p>
                    <p className="mt-1 text-sm text-gray-600">
                        Each works once, if you lose your phone. They are shown
                        only now.
                    </p>
                    <ul className="mt-3 grid grid-cols-2 gap-1 font-mono text-sm">
                        {twoFactor.new_recovery_codes.map((code) => (
                            <li key={code}>{code}</li>
                        ))}
                    </ul>
                </div>
            )}

            {!twoFactor.enabled && !twoFactor.setup && (
                <div className="mt-6">
                    <PrimaryButton
                        onClick={() =>
                            router.post(
                                route('two-factor.enable'),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Set up two-factor sign-in
                    </PrimaryButton>
                </div>
            )}

            {twoFactor.setup && (
                <form onSubmit={submitConfirm} className="mt-6 space-y-4">
                    <p className="text-sm text-gray-600">
                        Scan this with your authenticator app, then enter the
                        6-digit code it shows to finish.
                    </p>
                    <div className="inline-block rounded-md bg-white p-3">
                        <QRCodeSVG value={twoFactor.setup.uri} size={160} />
                    </div>
                    <p className="text-xs text-gray-600">
                        Can't scan? Enter this key instead:{' '}
                        <span className="select-all font-mono">
                            {twoFactor.setup.secret}
                        </span>
                    </p>
                    <div className="max-w-xs">
                        <InputLabel htmlFor="tf_code" value="Code" />
                        <TextInput
                            id="tf_code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={confirm.data.code}
                            className="mt-1 block w-full"
                            onChange={(e) =>
                                confirm.setData('code', e.target.value)
                            }
                        />
                        <InputError
                            message={confirm.errors.code}
                            className="mt-2"
                        />
                    </div>
                    <div className="flex items-center gap-3">
                        <PrimaryButton disabled={confirm.processing}>
                            Turn on
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            onClick={() => setAsking('disable')}
                        >
                            Cancel setup
                        </SecondaryButton>
                    </div>
                </form>
            )}

            {twoFactor.enabled && (
                <div className="mt-6 space-y-4">
                    <p className="text-sm text-gray-900">
                        Two-factor sign-in is <strong>on</strong>.{' '}
                        <span className="text-gray-600">
                            {twoFactor.recovery_codes_left} recovery code
                            {twoFactor.recovery_codes_left === 1
                                ? ''
                                : 's'}{' '}
                            left.
                        </span>
                    </p>
                    {asking === null && (
                        <div className="flex gap-3">
                            <SecondaryButton onClick={() => setAsking('codes')}>
                                New recovery codes
                            </SecondaryButton>
                            <DangerButton onClick={() => setAsking('disable')}>
                                Turn off
                            </DangerButton>
                        </div>
                    )}
                </div>
            )}

            {asking !== null && (
                <form
                    onSubmit={submitPassword}
                    className="mt-4 max-w-xs space-y-3"
                >
                    <div>
                        <InputLabel
                            htmlFor="tf_password"
                            value="Confirm your password"
                        />
                        <TextInput
                            id="tf_password"
                            type="password"
                            autoComplete="current-password"
                            isFocused={true}
                            value={passwordForm.data.password}
                            className="mt-1 block w-full"
                            onChange={(e) =>
                                passwordForm.setData('password', e.target.value)
                            }
                        />
                        <InputError
                            message={passwordForm.errors.password}
                            className="mt-2"
                        />
                    </div>
                    <div className="flex gap-3">
                        <PrimaryButton disabled={passwordForm.processing}>
                            {asking === 'disable'
                                ? twoFactor.enabled
                                    ? 'Turn off'
                                    : 'Cancel setup'
                                : 'Generate codes'}
                        </PrimaryButton>
                        <SecondaryButton
                            type="button"
                            onClick={() => {
                                passwordForm.reset();
                                setAsking(null);
                            }}
                        >
                            Never mind
                        </SecondaryButton>
                    </div>
                </form>
            )}
        </section>
    );
}
