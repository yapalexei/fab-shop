import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SearchableSelect from '@/Components/SearchableSelect';
import TextInput from '@/Components/TextInput';
import OnboardingLayout from '@/Layouts/OnboardingLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface PlanOption {
    key: string;
    name: string;
    user_limit: number;
}

export default function ShopStep({
    plans,
    timezones,
}: PageProps<{ plans: PlanOption[]; timezones: string[] }>) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        plan: 'free',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.shop'));
    };

    return (
        <OnboardingLayout step="shop">
            <Head title="Set up your shop" />

            <h1 className="text-xl font-semibold text-gray-900">
                Let's set up your shop
            </h1>
            <p className="mt-1 text-sm text-gray-500">
                A couple of quick questions, then you're in.
            </p>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <FormErrorSummary errors={errors} />

                <div>
                    <InputLabel htmlFor="name" value="Shop name" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        isFocused
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="timezone" value="Timezone" />
                    <SearchableSelect
                        id="timezone"
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        wrapperClassName="mt-1"
                        value={data.timezone}
                        onChange={(value) => setData('timezone', value)}
                        options={timezones.map((tz) => ({
                            value: tz,
                            label: tz.replaceAll('_', ' '),
                        }))}
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        Dates on the dashboard and your quotes follow this
                        clock - changeable later in Shop settings.
                    </p>
                    <InputError message={errors.timezone} className="mt-2" />
                </div>

                <div>
                    <InputLabel value="Plan" />
                    <div className="mt-1 space-y-2">
                        {plans.map((plan) => (
                            <label
                                key={plan.key}
                                className={`flex cursor-pointer items-center justify-between rounded-md border px-4 py-3 ${
                                    data.plan === plan.key
                                        ? 'border-indigo-300 bg-indigo-50'
                                        : 'border-gray-200 hover:bg-gray-50'
                                }`}
                            >
                                <span className="flex items-center gap-3">
                                    <input
                                        type="radio"
                                        name="plan"
                                        value={plan.key}
                                        checked={data.plan === plan.key}
                                        onChange={() =>
                                            setData('plan', plan.key)
                                        }
                                        className="text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span className="text-sm font-medium text-gray-900">
                                        {plan.name}
                                    </span>
                                </span>
                                <span className="text-xs text-gray-500">
                                    Up to {plan.user_limit} user
                                    {plan.user_limit === 1 ? '' : 's'}
                                </span>
                            </label>
                        ))}
                    </div>
                    <p className="mt-2 text-xs text-gray-500">
                        Every plan starts with a full-access, 14-day free
                        trial, so this only decides what applies once the
                        trial ends -{' '}
                        <a
                            href={route('marketing')}
                            target="_blank"
                            rel="noreferrer"
                            className="text-indigo-600 hover:text-indigo-900"
                        >
                            compare plans
                        </a>
                        .
                    </p>
                    <InputError message={errors.plan} className="mt-2" />
                </div>

                <div className="flex justify-end pt-2">
                    <PrimaryButton disabled={processing}>
                        Continue
                    </PrimaryButton>
                </div>
            </form>
        </OnboardingLayout>
    );
}
