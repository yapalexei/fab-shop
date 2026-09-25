import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function ShopSuspended({ shopName }: { shopName: string }) {
    return (
        <GuestLayout>
            <Head title="Shop suspended" />

            <div className="text-center">
                <h1 className="text-lg font-semibold text-gray-900">
                    {shopName} has been suspended
                </h1>
                <p className="mt-2 text-sm text-gray-600">
                    Access to this shop is temporarily locked. Nothing has been
                    deleted - contact support to sort this out.
                </p>
            </div>

            <div className="mt-6 flex justify-center">
                <Link href={route('logout')} method="post" as="button">
                    <PrimaryButton>Log Out</PrimaryButton>
                </Link>
            </div>
        </GuestLayout>
    );
}
