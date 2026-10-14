import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SearchableSelect from '@/Components/SearchableSelect';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * First-run page of a self-hosted install: creates the owner account and the
 * shop. The server closes it for good as soon as any account exists.
 */
export default function Setup({
    timezones,
    setupKey,
}: {
    timezones: string[];
    setupKey: string | null;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        shop_name: '',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        // Present only when the install is protected by a setup key.
        key: setupKey ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('setup.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Set up your shop" />

            <h1 className="text-xl font-semibold text-gray-900">
                Welcome - let's set up your shop
            </h1>
            <p className="mt-1 text-sm text-gray-500">
                Create the owner account for this install. You can invite your
                team afterwards.
            </p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <FormErrorSummary errors={errors} />

                <div>
                    <InputLabel htmlFor="shop_name" value="Shop name" />
                    <TextInput
                        id="shop_name"
                        className="mt-1 block w-full"
                        value={data.shop_name}
                        onChange={(e) => setData('shop_name', e.target.value)}
                        isFocused
                        required
                    />
                    <InputError message={errors.shop_name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="timezone" value="Timezone" />
                    <SearchableSelect
                        id="timezone"
                        className="block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                        wrapperClassName="mt-1"
                        value={data.timezone}
                        onChange={(value) => setData('timezone', value)}
                        options={timezones.map((tz) => ({
                            value: tz,
                            label: tz.replaceAll('_', ' '),
                        }))}
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        Dates on the dashboard and your quotes follow this clock
                        - changeable later in Shop settings.
                    </p>
                    <InputError message={errors.timezone} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="name" value="Your name" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        autoComplete="name"
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        className="mt-1 block w-full"
                        value={data.password}
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
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
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />
                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <div className="flex justify-end">
                    <PrimaryButton disabled={processing}>
                        Create shop
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
