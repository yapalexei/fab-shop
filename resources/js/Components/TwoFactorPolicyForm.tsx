import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Checkbox } from '@/Components/ui/checkbox';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export interface PolicyValues {
    roles: string[];
    grace_days: number;
}

/**
 * Who must use two-factor sign-in and how long they have to set it up. Used
 * on the platform admin page (the default for every shop) and on the shop
 * settings page, where `platformDefault` is given and the owner can instead
 * choose to follow that default.
 */
export default function TwoFactorPolicyForm({
    action,
    roleOptions,
    maxGraceDays,
    values,
    platformDefault,
    followsDefault,
}: {
    action: string;
    roleOptions: string[];
    maxGraceDays: number;
    values: PolicyValues;
    platformDefault?: PolicyValues;
    followsDefault?: boolean;
}) {
    const isShop = platformDefault !== undefined;
    const { data, setData, put, processing, errors } = useForm({
        mode: followsDefault ? 'inherit' : 'custom',
        roles: values.roles,
        grace_days: values.grace_days,
    });

    const describe = (policy: PolicyValues) =>
        policy.roles.length === 0
            ? 'nobody is required'
            : `${policy.roles.join(', ')} - ${policy.grace_days} day${policy.grace_days === 1 ? '' : 's'} to set up`;

    const toggle = (role: string, on: boolean) =>
        setData(
            'roles',
            on ? [...data.roles, role] : data.roles.filter((r) => r !== role),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(action, { preserveScroll: true });
    };

    const editable = !isShop || data.mode === 'custom';

    return (
        <form onSubmit={submit} className="space-y-4">
            {isShop && (
                <div className="space-y-2 text-sm">
                    <label className="flex items-start gap-2">
                        <input
                            type="radio"
                            name="mode"
                            checked={data.mode === 'inherit'}
                            onChange={() => setData('mode', 'inherit')}
                            className="mt-1"
                        />
                        <span>
                            Follow the platform default
                            <span className="block text-gray-500">
                                Currently: {describe(platformDefault!)}.
                            </span>
                        </span>
                    </label>
                    <label className="flex items-start gap-2">
                        <input
                            type="radio"
                            name="mode"
                            checked={data.mode === 'custom'}
                            onChange={() => setData('mode', 'custom')}
                            className="mt-1"
                        />
                        <span>
                            Set my own requirement
                            <span className="block text-gray-500">
                                Pick nobody to turn the requirement off for this
                                shop.
                            </span>
                        </span>
                    </label>
                </div>
            )}

            <fieldset
                disabled={!editable}
                className="space-y-3 disabled:opacity-50"
            >
                <legend className="text-sm font-medium text-gray-900">
                    Require two-factor for
                </legend>
                <div className="flex flex-wrap gap-6">
                    {roleOptions.map((role) => (
                        <label
                            key={role}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={data.roles.includes(role)}
                                onCheckedChange={(v) =>
                                    toggle(role, v === true)
                                }
                            />
                            {role}
                        </label>
                    ))}
                </div>
                <InputError message={errors.roles} />

                <div className="max-w-xs">
                    <label
                        htmlFor="grace_days"
                        className="text-sm font-medium text-gray-900"
                    >
                        Days to set it up
                    </label>
                    <TextInput
                        id="grace_days"
                        type="number"
                        min={1}
                        max={maxGraceDays}
                        value={data.grace_days}
                        onChange={(e) =>
                            setData('grace_days', Number(e.target.value))
                        }
                        className="mt-1 block w-full"
                    />
                    <InputError message={errors.grace_days} className="mt-1" />
                    <p className="mt-1 text-xs text-gray-500">
                        Counted from today for people already covered, or from
                        the day they join. After that they must finish setup
                        before they can keep working.
                    </p>
                </div>
            </fieldset>

            <p className="text-xs text-gray-500">
                Saving emails everyone who is newly required to set it up.
            </p>
            <PrimaryButton disabled={processing}>
                Save requirement
            </PrimaryButton>
        </form>
    );
}
