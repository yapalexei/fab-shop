import FormErrorSummary from '@/Components/FormErrorSummary';
import InputLabel from '@/Components/InputLabel';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';

interface FeatureSettingRow {
    feature: string;
    label: string;
    price_cents: number;
}

export default function Features({
    settings,
    defaultTrialDays,
}: PageProps<{ settings: FeatureSettingRow[]; defaultTrialDays: number }>) {
    const { data, setData, patch, processing, errors } = useForm<{
        default_trial_days: number;
        settings: FeatureSettingRow[];
    }>({ default_trial_days: defaultTrialDays, settings });

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

    return (
        <PlatformAdminLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Feature pricing
                    </h2>
                    <PageHelp>
                        <p>
                            What each feature costs as a standalone add-on.
                            These prices aren't charged automatically yet - no
                            payment processor is wired up - this is the source
                            of truth the rest of the app will read once add-on
                            subscriptions are built.
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
                    <Card className="p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary
                                errors={errors as Record<string, string>}
                            />

                            <div className="mb-6 border-b border-gray-100 pb-6">
                                <InputLabel
                                    htmlFor="default-trial-days"
                                    value="Default trial length for new shops"
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    What a brand-new signup's trial lasts, and
                                    what the marketing page advertises - the
                                    same number, always, so marketing can never
                                    promise more (or less) than a real signup
                                    gets. Existing shops keep whatever their
                                    trial was set to at signup.
                                </p>
                                <div className="mt-1 flex items-center gap-2">
                                    <TextInput
                                        id="default-trial-days"
                                        type="number"
                                        min="0"
                                        className="w-24"
                                        value={data.default_trial_days}
                                        onChange={(e) =>
                                            setData(
                                                'default_trial_days',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <span className="text-sm text-gray-500">
                                        days
                                    </span>
                                </div>
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
                                    </div>
                                ))}
                            </div>

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Save
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </PlatformAdminLayout>
    );
}
