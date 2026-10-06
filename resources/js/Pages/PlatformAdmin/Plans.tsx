import FormErrorSummary from '@/Components/FormErrorSummary';
import PageHelp from '@/Components/PageHelp';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';

interface PlanRow {
    id: number;
    key: string;
    name: string;
    user_limit: number | null;
    features: string[];
}

interface FeatureOption {
    key: string;
    label: string;
    shipped: boolean;
}

export default function Plans({
    plans,
    allFeatures,
}: PageProps<{ plans: PlanRow[]; allFeatures: FeatureOption[] }>) {
    const { data, setData, patch, processing, errors } = useForm<{
        plans: PlanRow[];
    }>({ plans });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('platform-admin.plans.update'));
    };

    const updateSeats = (index: number, value: string) => {
        const next = [...data.plans];
        next[index] = {
            ...next[index],
            user_limit: value === '' ? null : Number(value),
        };
        setData('plans', next);
    };

    const toggleFeature = (
        index: number,
        featureKey: string,
        checked: boolean,
    ) => {
        const next = [...data.plans];
        const current = next[index].features;
        next[index] = {
            ...next[index],
            features: checked
                ? [...current, featureKey]
                : current.filter((key) => key !== featureKey),
        };
        setData('plans', next);
    };

    return (
        <PlatformAdminLayout
            header={
                <div className="flex items-center gap-2">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Plan settings
                    </h2>
                    <PageHelp>
                        <p>
                            Each tier's default seat count and which features it
                            bundles - the same data the marketing page's tier
                            comparison and the app's own feature gating both
                            read live, so a change here takes effect everywhere
                            at once.
                        </p>
                        <p>
                            A shop's actual seat limit or plan can still be
                            overridden individually from the shop directory -
                            this page only sets each tier's own default.
                        </p>
                        <p>
                            The Free tier's checked features here barely matter
                            in practice: a shop on Free is either still inside
                            its trial (which grants every feature regardless of
                            plan) or past it, in which case Free was never meant
                            to grant anything further.
                        </p>
                    </PageHelp>
                </div>
            }
        >
            <Head title="Plan settings" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    <Card className="overflow-x-auto p-4 sm:p-8">
                        <form onSubmit={submit}>
                            <FormErrorSummary
                                errors={errors as Record<string, string>}
                            />

                            <table className="min-w-full">
                                <thead>
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            Feature
                                        </th>
                                        {data.plans.map((plan) => (
                                            <th
                                                key={plan.id}
                                                className="px-3 py-2 text-center text-xs font-medium uppercase tracking-wider text-gray-500"
                                            >
                                                {plan.name}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    <tr>
                                        <td className="whitespace-nowrap px-3 py-3 text-sm font-medium text-gray-700">
                                            Default seats
                                        </td>
                                        {data.plans.map((plan, index) => (
                                            <td
                                                key={plan.id}
                                                className="px-3 py-3 text-center"
                                            >
                                                <TextInput
                                                    type="number"
                                                    min="1"
                                                    placeholder="unlimited"
                                                    className="w-24 text-center"
                                                    value={
                                                        plan.user_limit ?? ''
                                                    }
                                                    onChange={(e) =>
                                                        updateSeats(
                                                            index,
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                    {allFeatures.map((feature) => (
                                        <tr key={feature.key}>
                                            <td className="whitespace-nowrap px-3 py-3 text-sm text-gray-700">
                                                {feature.label}
                                                {!feature.shipped && (
                                                    <Badge variant="warning" className="ms-2">
                                                        Coming soon
                                                    </Badge>
                                                )}
                                            </td>
                                            {data.plans.map((plan, index) => (
                                                <td
                                                    key={plan.id}
                                                    className="px-3 py-3 text-center"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        aria-label={`${plan.name}: ${feature.label}`}
                                                        className="rounded-sm border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        checked={plan.features.includes(
                                                            feature.key,
                                                        )}
                                                        onChange={(e) =>
                                                            toggleFeature(
                                                                index,
                                                                feature.key,
                                                                e.target
                                                                    .checked,
                                                            )
                                                        }
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

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
