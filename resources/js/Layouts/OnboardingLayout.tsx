import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

const steps = [
    { key: 'shop', label: 'Shop' },
    { key: 'team', label: 'Team' },
    { key: 'catalog', label: 'Catalog' },
];

function StepIndicator({ current }: { current: string }) {
    const currentIndex = steps.findIndex((step) => step.key === current);

    return (
        <ol className="flex items-center justify-center gap-2">
            {steps.map((step, index) => {
                const done = index < currentIndex;
                const active = index === currentIndex;

                return (
                    <li key={step.key} className="flex items-center gap-2">
                        <span
                            className={`flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold ${
                                active
                                    ? 'bg-indigo-600 text-white'
                                    : done
                                      ? 'bg-indigo-100 text-indigo-700'
                                      : 'bg-gray-100 text-gray-400'
                            }`}
                        >
                            {index + 1}
                        </span>
                        <span
                            className={`text-sm ${active ? 'font-semibold text-gray-900' : 'text-gray-500'}`}
                        >
                            {step.label}
                        </span>
                        {index < steps.length - 1 && (
                            <span className="mx-1 h-px w-6 bg-gray-200" />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

/**
 * The onboarding wizard's shell - deliberately not AuthenticatedLayout
 * (there's no shop yet for most of it, so the real nav would be mostly
 * dead links) and not the narrow GuestLayout (team/catalog steps need more
 * width than a login form does).
 */
export default function OnboardingLayout({
    step,
    children,
}: PropsWithChildren<{ step: 'shop' | 'team' | 'catalog' }>) {
    return (
        <div className="min-h-screen bg-gray-50">
            <header className="border-b border-gray-100 bg-white">
                <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-4 sm:px-6">
                    <Link
                        href="/"
                        className="flex items-center gap-2 text-gray-900"
                    >
                        <ApplicationLogo className="h-7 w-7 fill-current text-gray-800" />
                        <span className="font-semibold">Fab Shop</span>
                    </Link>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-sm text-gray-500 hover:text-gray-700"
                    >
                        Log out
                    </Link>
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <div className="mb-8">
                    <StepIndicator current={step} />
                </div>

                <div className="rounded-lg bg-white p-6 shadow-sm sm:p-8">
                    {children}
                </div>
            </main>
        </div>
    );
}
