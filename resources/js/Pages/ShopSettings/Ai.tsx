import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import McpConnectionPanel, { McpData } from '@/Components/McpConnectionPanel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Setting {
    provider: string;
    model: string;
    has_key: boolean;
    key_hint: string | null;
    last_tested_at: string | null;
}

interface Provider {
    label: string;
    default_model: string;
    models: Record<string, string>;
}

const selectClass =
    'mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500';

export default function Ai({
    setting,
    available,
    agentAvailable,
    providers,
    mcp,
}: PageProps<{
    setting: Setting | null;
    available: boolean;
    agentAvailable: boolean;
    providers: Record<string, Provider>;
    mcp: McpData;
}>) {
    const firstProvider = Object.keys(providers)[0];
    const { data, setData, put, processing, errors, reset } = useForm({
        provider: setting?.provider ?? firstProvider,
        model: setting?.model ?? providers[firstProvider].default_model,
        // Never pre-filled: the stored key is not sent to the browser.
        api_key: '',
    });

    const changeProvider = (provider: string) => {
        setData((current) => ({
            ...current,
            provider,
            model: providers[provider].default_model,
            api_key: '',
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('shop.ai.update'), {
            preserveScroll: true,
            onSuccess: () => reset('api_key'),
        });
    };

    const remove = () => {
        if (!confirm('Remove your AI provider and key from this shop?')) {
            return;
        }
        router.delete(route('shop.ai.destroy'), { preserveScroll: true });
    };

    const switchingProvider =
        setting !== null && data.provider !== setting.provider;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    AI assistant
                </h2>
            }
        >
            <Head title="AI assistant" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {!available && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p className="font-semibold">AI is paused</p>
                            <p className="mt-1">
                                The AI assistant is part of the Shop plan
                                and above. Your provider and key are saved and
                                will pick up where they left off once the shop
                                is subscribed.{' '}
                                <Link
                                    href={route('shop.plan.index')}
                                    className="font-medium underline"
                                >
                                    View plans
                                </Link>
                            </p>
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="space-y-6 bg-white p-4 shadow-sm sm:rounded-lg sm:p-8"
                    >
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900">
                                Your AI provider
                            </h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Bring your own account. You pay your provider
                                directly for what you use. When AI features are
                                used, the text involved (for example a
                                customer&apos;s email you paste in, and the
                                names in your catalog) is sent to the provider
                                you choose here, under your account and its
                                terms.
                            </p>
                        </div>

                        <FormErrorSummary
                            errors={errors as Record<string, string>}
                        />

                        <fieldset
                            disabled={!available}
                            className="space-y-4 disabled:opacity-60"
                        >
                            <div>
                                <InputLabel
                                    htmlFor="provider"
                                    value="Provider"
                                />
                                <select
                                    id="provider"
                                    className={selectClass}
                                    value={data.provider}
                                    onChange={(e) =>
                                        changeProvider(e.target.value)
                                    }
                                >
                                    {Object.entries(providers).map(
                                        ([key, provider]) => (
                                            <option key={key} value={key}>
                                                {provider.label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError
                                    message={errors.provider}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel htmlFor="model" value="Model" />
                                <select
                                    id="model"
                                    className={selectClass}
                                    value={data.model}
                                    onChange={(e) =>
                                        setData('model', e.target.value)
                                    }
                                >
                                    {Object.entries(
                                        providers[data.provider].models,
                                    ).map(([key, label]) => (
                                        <option key={key} value={key}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.model}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel htmlFor="api_key" value="API key" />
                                <TextInput
                                    id="api_key"
                                    type="password"
                                    autoComplete="off"
                                    className="mt-1 block w-full"
                                    value={data.api_key}
                                    placeholder={
                                        setting?.has_key && !switchingProvider
                                            ? `Saved (ends in ${setting.key_hint}) - leave blank to keep`
                                            : 'Paste your API key'
                                    }
                                    onChange={(e) =>
                                        setData('api_key', e.target.value)
                                    }
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Stored encrypted and never shown again.
                                    Saving makes one small test request to
                                    confirm the key works.
                                </p>
                                <InputError
                                    message={errors.api_key}
                                    className="mt-2"
                                />
                            </div>
                        </fieldset>

                        <div className="flex items-center gap-3">
                            <PrimaryButton
                                disabled={processing || !available}
                            >
                                {processing
                                    ? 'Testing connection…'
                                    : 'Save and test connection'}
                            </PrimaryButton>
                            {setting?.has_key && (
                                <SecondaryButton
                                    type="button"
                                    onClick={remove}
                                    disabled={!available}
                                >
                                    Remove key
                                </SecondaryButton>
                            )}
                            {setting?.last_tested_at && (
                                <span className="text-xs text-gray-500">
                                    Last verified{' '}
                                    {new Date(
                                        setting.last_tested_at,
                                    ).toLocaleDateString()}
                                </span>
                            )}
                        </div>
                    </form>

                    {available && !agentAvailable && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p className="font-semibold">
                                AI agent access is a Pro feature
                            </p>
                            <p className="mt-1">
                                Connecting your own AI assistant to your shop
                                data is part of the Pro plan.{' '}
                                <Link
                                    href={route('shop.plan.index')}
                                    className="font-medium underline"
                                >
                                    View plans
                                </Link>
                            </p>
                        </div>
                    )}
                    <McpConnectionPanel
                        mcp={mcp}
                        available={available && agentAvailable}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
