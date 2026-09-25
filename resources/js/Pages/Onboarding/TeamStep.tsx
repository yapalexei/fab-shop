import FormErrorSummary from '@/Components/FormErrorSummary';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import OnboardingLayout from '@/Layouts/OnboardingLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface InvitationRow {
    email: string;
    role: string;
}

export default function TeamStep({
    shopName,
    roles,
    invited,
}: PageProps<{ shopName: string; roles: string[]; invited: InvitationRow[] }>) {
    const { data, setData, post, processing, errors } = useForm<{
        invitations: InvitationRow[];
    }>({ invitations: [{ email: '', role: roles[roles.length - 1] ?? 'Staff' }] });

    const errorMap = errors as Record<string, string>;

    const update = (index: number, patch: Partial<InvitationRow>) => {
        const next = [...data.invitations];
        next[index] = { ...next[index], ...patch };
        setData('invitations', next);
    };

    const addRow = () =>
        setData('invitations', [
            ...data.invitations,
            { email: '', role: roles[roles.length - 1] ?? 'Staff' },
        ]);

    const removeRow = (index: number) =>
        setData(
            'invitations',
            data.invitations.filter((_, i) => i !== index),
        );

    // Blank rows (someone clicked "Add another" then changed their mind)
    // are dropped server-side before validation, not filtered here - state
    // updates from setData aren't visible to a post() called synchronously
    // right after it, so filtering here and posting in the same handler
    // would submit the pre-filter rows.
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.team'));
    };

    // A real router.post with an explicit, literal payload - not useForm's
    // post(), which always sends its own (possibly stale) data state -
    // so "Skip" reliably sends no invitations regardless of what's typed.
    const skip = () => {
        router.post(route('onboarding.team'), { invitations: [] });
    };

    return (
        <OnboardingLayout step="team">
            <Head title="Invite your team" />

            <h1 className="text-xl font-semibold text-gray-900">
                Invite your team to {shopName}
            </h1>
            <p className="mt-1 text-sm text-gray-500">
                Optional - you can always invite people later from Team
                settings.
            </p>

            {invited.length > 0 && (
                <div className="mt-4 rounded-md bg-gray-50 p-3">
                    <p className="text-xs font-medium uppercase tracking-wider text-gray-500">
                        Already invited
                    </p>
                    <ul className="mt-1 space-y-0.5 text-sm text-gray-700">
                        {invited.map((row) => (
                            <li key={row.email}>
                                {row.email} - {row.role}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-4">
                <FormErrorSummary errors={errorMap} />

                <div className="space-y-3">
                    {data.invitations.map((row, index) => (
                        <div key={index} className="flex items-start gap-2">
                            <div className="flex-1">
                                <TextInput
                                    type="email"
                                    placeholder="email@example.com"
                                    className={`block w-full ${errorMap[`invitations.${index}.email`] ? 'border-red-400' : ''}`}
                                    value={row.email}
                                    onChange={(e) =>
                                        update(index, { email: e.target.value })
                                    }
                                />
                            </div>
                            <select
                                className="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={row.role}
                                onChange={(e) =>
                                    update(index, { role: e.target.value })
                                }
                            >
                                {roles.map((role) => (
                                    <option key={role} value={role}>
                                        {role}
                                    </option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={() => removeRow(index)}
                                className="px-2 py-2 text-sm text-gray-400 hover:text-red-600"
                                aria-label="Remove"
                            >
                                &times;
                            </button>
                        </div>
                    ))}
                </div>

                <button
                    type="button"
                    onClick={addRow}
                    className="text-sm font-medium text-indigo-600 hover:text-indigo-900"
                >
                    + Add another
                </button>

                <div className="flex items-center justify-between border-t border-gray-100 pt-4">
                    <SecondaryButton onClick={skip} disabled={processing}>
                        Skip for now
                    </SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        Continue
                    </PrimaryButton>
                </div>
            </form>
        </OnboardingLayout>
    );
}
