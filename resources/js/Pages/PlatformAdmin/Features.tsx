import FormErrorSummary from '@/Components/FormErrorSummary';
import InputLabel from '@/Components/InputLabel';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface FeatureSettingRow {
    feature: string;
    label: string;
    price_cents: number;
    trial_days: number;
}

export default function Features({
    settings,
}: PageProps<{ settings: FeatureSettingRow[] }>) {
    const { data, setData, patch, processing, errors } = useForm<{
        settings: FeatureSettingRow[];
    }>({ settings });

    const [bulkTrialDays, setBulkTrialDays] = useState('');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('platform-admin.features.update'));
    };

    const updatePrice = (index: number, dollars: string) => {
        const next = [...data.settings];
        next[index] = {
            ...next[index],
            price_cents: Math.round(Number(dollars) * 100),
        };
        setData('settings', next);
    };

    const updateTrialDays = (index: number, days: string) => {
        const next = [...data.settings];
        next[index] = { ...next[index], trial_days: Number(days) };
        setData('settings', next);
    };

    const applyTrialDaysToAll = () => {
        if (bulkTrialDays === '') {
            return;
        }

        const days = Number(bulkTrialDays);
        setData(
            'settings',
            data.settings.map((row) => ({ ...row, trial_days: days })),
        );
    };

    return (
        <PlatformAdminLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Feature pricing
                    </h2>
                    <PageHelp>
                        <p>
                            What each feature costs as a standalone add-on, and
                            how many days it's free during a shop's trial. These
                            prices aren't charged automatically yet - no payment
                            processor is wired up - this is the source of truth
                            the rest of the app will read once add-on
                            subscriptions and per-feature trials are built.
                        </p>
                        <p>
                            A feature already bundled into a shop's plan is
                            never billed again as an add-on on top of it.
                        </p>
                    </PageHelp>
                </div>
            }
        >
            <Head title="Feature pricing" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary
                                errors={errors as Record<string, string>}
                            />

                            <div className="mb-6 flex items-end gap-2 border-b border-gray-100 pb-6">
                                <div>
                                    <InputLabel
                                        htmlFor="bulk-trial-days"
                                        value="Set trial days for every feature"
                                    />
                                    <TextInput
                                        id="bulk-trial-days"
                                        type="number"
                                        min="0"
                                        className="mt-1 w-24"
                                        value={bulkTrialDays}
                                        onChange={(e) =>
                                            setBulkTrialDays(e.target.value)
                                        }
                                    />
                                </div>
                                <SecondaryButton
                                    type="button"
                                    onClick={applyTrialDaysToAll}
                                >
                                    Apply to all
                                </SecondaryButton>
                            </div>

                            <div className="space-y-4">
                                {data.settings.map((row, index) => (
                                    <div
                                        key={row.feature}
                                        className="flex items-center justify-between gap-4"
                                    >
                                        <InputLabel
                                            htmlFor={`price-${row.feature}`}
                                            value={row.label}
                                            className="flex-1"
                                        />
                                        <div className="flex items-center gap-1">
                                            <span className="text-sm text-gray-500">
                                                $
                                            </span>
                                            <TextInput
                                                id={`price-${row.feature}`}
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                className="w-24"
                                                value={row.price_cents / 100}
                                                onChange={(e) =>
                                                    updatePrice(
                                                        index,
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <span className="text-sm text-gray-500">
                                                / mo
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <TextInput
                                                id={`trial-${row.feature}`}
                                                type="number"
                                                min="0"
                                                className="w-20"
                                                value={row.trial_days}
                                                onChange={(e) =>
                                                    updateTrialDays(
                                                        index,
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <span className="text-sm text-gray-500">
                                                trial days
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Save
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </PlatformAdminLayout>
    );
}
