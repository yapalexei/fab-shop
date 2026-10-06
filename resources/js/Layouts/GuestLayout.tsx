import ApplicationLogo from '@/Components/ApplicationLogo';
import LegalLinks from '@/Components/LegalLinks';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { Card } from '@/Components/ui/card';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="relative flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0">
            <ThemeToggle className="absolute right-4 top-4" />

            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-gray-500" />
                </Link>
            </div>

            <Card className="mt-6 w-full overflow-hidden px-6 py-4 sm:max-w-md">
                {children}
            </Card>

            <LegalLinks className="my-6" />
        </div>
    );
}
