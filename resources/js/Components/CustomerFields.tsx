import { LogoAvatar } from '@/Components/Avatar';
import ImageField from '@/Components/ImageField';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export interface CustomerFormData {
    name: string;
    email: string;
    phone: string;
    address: string;
    notes: string;
    /** A newly chosen logo, or null to keep the current one. */
    logo: File | null;
    remove_logo: boolean;
    [key: string]: string | boolean | File | null;
}

export default function CustomerFields({
    data,
    setData,
    errors,
    currentLogo,
}: {
    data: CustomerFormData;
    setData: (
        field: keyof CustomerFormData,
        value: string | boolean | File | null,
    ) => void;
    errors: Partial<Record<keyof CustomerFormData, string>>;
    /** The logo already saved for this customer (edit page). */
    currentLogo?: string | null;
}) {
    return (
        <>
            <ImageField
                label="Logo"
                preview={(src) => (
                    <LogoAvatar src={src} className="size-16" />
                )}
                current={currentLogo}
                file={data.logo}
                removed={data.remove_logo}
                onChange={(file) => setData('logo', file)}
                onRemove={(removed) => setData('remove_logo', removed)}
                error={errors.logo}
            />

            <div className="mt-4">
                <InputLabel htmlFor="name" value="Name" />
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

            <div className="mt-4">
                <InputLabel htmlFor="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    className="mt-1 block w-full"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                />
                <InputError message={errors.email} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="phone" value="Phone" />
                <TextInput
                    id="phone"
                    className="mt-1 block w-full"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                />
                <InputError message={errors.phone} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="address" value="Address" />
                <textarea
                    id="address"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                    rows={3}
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                />
                <InputError message={errors.address} className="mt-2" />
            </div>

            <div className="mt-4">
                <InputLabel htmlFor="notes" value="Notes" />
                <textarea
                    id="notes"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                    rows={3}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <InputError message={errors.notes} className="mt-2" />
            </div>
        </>
    );
}
