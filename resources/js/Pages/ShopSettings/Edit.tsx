import FormErrorSummary from '@/Components/FormErrorSummary';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SearchableSelect from '@/Components/SearchableSelect';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Settings {
    name: string;
    timezone: string | null;
    stale_quote_days: number | null;
    win_rate_window_days: number | null;
    job_board_drag_enabled: boolean;
    quote_emailing_enabled: boolean;
}

interface Defaults {
    timezone: string;
    stale_quote_days: number;
    win_rate_window_days: number;
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-4 border-t border-gray-100 py-6 first:border-t-0 first:pt-0 md:grid-cols-3">
            <div>
                <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
                <p className="mt-1 text-sm text-gray-500">{description}</p>
            </div>
            <div className="space-y-4 md:col-span-2">{children}</div>
        </div>
    );
}

export default function Edit({
    settings,
    defaults,
    timezones,
    quoteEmailingAvailable,
}: PageProps<{
    settings: Settings;
    defaults: Defaults;
    timezones: string[];
    quoteEmailingAvailable: boolean;
}>) {
    // Blank inputs are sent as null, which means "use the default" - the
    // placeholder shows what that default currently is.
    const { data, setData, patch, processing, errors, isDirty } = useForm({
        name: settings.name,
        timezone: settings.timezone ?? '',
        stale_quote_days: settings.stale_quote_days?.toString() ?? '',
        win_rate_window_days: settings.win_rate_window_days?.toString() ?? '',
        job_board_drag_enabled: settings.job_board_drag_enabled,
        quote_emailing_enabled: settings.quote_emailing_enabled,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('shop.settings.update'), { preserveScroll: true });
    };

    const now = new Date();
    const localTime = (timeZone: string) =>
        new Intl.DateTimeFormat('en-US', {
            timeZone,
            weekday: 'short',
            hour: 'numeric',
            minute: '2-digit',
            timeZoneName: 'short',
        }).format(now);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Shop settings
                </h2>
            }
        >
            <Head title="Shop settings" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="bg-white p-4 shadow-sm sm:rounded-lg sm:p-8"
                    >
                        <FormErrorSummary
                            errors={errors as Record<string, string>}
                        />

                        <Section
                            title="Shop"
                            description="How your shop appears across the app and on quotes."
                        >
                            <div>
                                <InputLabel htmlFor="name" value="Shop name" />
                                <TextInput
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>
                        </Section>

                        <Section
                            title="Timezone"
                            description="Where your shop physically is. Dates on the dashboard, job history, quotes and invoices follow this clock - so does when “this month” starts and when your trial ends."
                        >
                            <div>
                                <InputLabel
                                    htmlFor="timezone"
                                    value="Timezone"
                                />
                                <SearchableSelect
                                    id="timezone"
                                    className="block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                                    wrapperClassName="mt-1"
                                    value={data.timezone}
                                    onChange={(value) => setData('timezone', value)}
                                    emptyLabel={`Default (${defaults.timezone})`}
                                    options={timezones.map((tz) => ({
                                        value: tz,
                                        label: tz.replaceAll('_', ' '),
                                    }))}
                                />
                                <p className="mt-1 text-xs text-gray-500">
                                    Right now it's{' '}
                                    {localTime(
                                        data.timezone || defaults.timezone,
                                    )}{' '}
                                    in the shop.
                                </p>
                                <InputError
                                    message={errors.timezone}
                                    className="mt-2"
                                />
                            </div>
                        </Section>

                        <Section
                            title="Dashboard"
                            description="Thresholds for the dashboard. Leave a field blank to use the default."
                        >
                            <div>
                                <InputLabel
                                    htmlFor="stale_quote_days"
                                    value="Flag a sent quote for follow-up after"
                                />
                                <div className="mt-1 flex items-center gap-2">
                                    <TextInput
                                        id="stale_quote_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        className="w-28"
                                        placeholder={defaults.stale_quote_days.toString()}
                                        value={data.stale_quote_days}
                                        onChange={(e) =>
                                            setData(
                                                'stale_quote_days',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <span className="text-sm text-gray-500">
                                        days without a reply
                                    </span>
                                </div>
                                <InputError
                                    message={errors.stale_quote_days}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="win_rate_window_days"
                                    value="Calculate win rate over the last"
                                />
                                <div className="mt-1 flex items-center gap-2">
                                    <TextInput
                                        id="win_rate_window_days"
                                        type="number"
                                        min="7"
                                        max="730"
                                        className="w-28"
                                        placeholder={defaults.win_rate_window_days.toString()}
                                        value={data.win_rate_window_days}
                                        onChange={(e) =>
                                            setData(
                                                'win_rate_window_days',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <span className="text-sm text-gray-500">
                                        days
                                    </span>
                                </div>
                                <InputError
                                    message={errors.win_rate_window_days}
                                    className="mt-2"
                                />
                            </div>
                        </Section>

                        <Section
                            title="Quotes"
                            description="How quotes reach your customers."
                        >
                            <label className="flex items-start gap-3">
                                <button
                                    type="button"
                                    role="switch"
                                    aria-checked={
                                        quoteEmailingAvailable &&
                                        data.quote_emailing_enabled
                                    }
                                    aria-labelledby="emailing-label"
                                    disabled={!quoteEmailingAvailable}
                                    onClick={() =>
                                        setData(
                                            'quote_emailing_enabled',
                                            !data.quote_emailing_enabled,
                                        )
                                    }
                                    className={`relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${quoteEmailingAvailable && data.quote_emailing_enabled ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-200 hover:bg-gray-300'}`}
                                >
                                    <span
                                        className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition ${quoteEmailingAvailable && data.quote_emailing_enabled ? 'translate-x-5' : 'translate-x-0'}`}
                                    />
                                </button>
                                <span>
                                    <span
                                        id="emailing-label"
                                        className="block text-sm font-medium text-gray-900"
                                    >
                                        Email quotes to customers
                                    </span>
                                    <span className="block text-sm text-gray-500">
                                        Adds a “Send to customer” button that
                                        emails the quote PDF. Off by default -
                                        you can still mark a quote as sent
                                        after handing it over yourself.
                                    </span>
                                    {!quoteEmailingAvailable && (
                                        <span className="mt-1 block text-xs text-amber-700">
                                            Emailing quotes is available on the
                                            Solo plan and above.
                                        </span>
                                    )}
                                </span>
                            </label>
                        </Section>

                        <Section
                            title="Job board"
                            description="How jobs move between stations on the board."
                        >
                            <label className="flex items-start gap-3">
                                <button
                                    type="button"
                                    role="switch"
                                    aria-checked={data.job_board_drag_enabled}
                                    aria-labelledby="drag-label"
                                    onClick={() =>
                                        setData(
                                            'job_board_drag_enabled',
                                            !data.job_board_drag_enabled,
                                        )
                                    }
                                    className={`relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${data.job_board_drag_enabled ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-200 hover:bg-gray-300'}`}
                                >
                                    <span
                                        className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition ${data.job_board_drag_enabled ? 'translate-x-5' : 'translate-x-0'}`}
                                    />
                                </button>
                                <span>
                                    <span
                                        id="drag-label"
                                        className="block text-sm font-medium text-gray-900"
                                    >
                                        Drag-and-drop on the job board
                                    </span>
                                    <span className="block text-sm text-gray-500">
                                        Let people drag a job card to another
                                        station. Every move - dragged or clicked
                                        - still asks for confirmation first. Off
                                        by default so a stray swipe on a shared
                                        shop-floor tablet can't move a job.
                                    </span>
                                </span>
                            </label>
                        </Section>

                        <div className="flex justify-end border-t border-gray-100 pt-6">
                            <PrimaryButton disabled={processing || !isDirty}>
                                Save settings
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
