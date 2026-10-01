import ApplicationLogo from '@/Components/ApplicationLogo';
import LegalLinks from '@/Components/LegalLinks';
import ThemeToggle from '@/Components/ThemeToggle';
import { Head, Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

/** Shared frame for the Terms, Privacy and SMS pages: public, readable, printable. */
export default function LegalLayout({
    title,
    effectiveDate,
    children,
}: PropsWithChildren<{ title: string; effectiveDate: string }>) {
    return (
        <div className="min-h-screen bg-gray-100">
            <Head title={title} />

            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex h-14 max-w-3xl items-center justify-between px-4 sm:px-6">
                    <Link
                        href="/"
                        className="flex items-center gap-2 text-gray-900"
                    >
                        <ApplicationLogo className="h-7 w-7 fill-current text-gray-800" />
                        <span className="font-semibold">Fab Shop</span>
                    </Link>
                    <ThemeToggle />
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
                <article className="rounded-lg bg-white p-6 shadow-sm sm:p-10">
                    <h1 className="text-2xl font-bold text-gray-900">
                        {title}
                    </h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Effective {effectiveDate}
                    </p>
                    <div className="mt-6 space-y-8 text-sm leading-6 text-gray-700">
                        {children}
                    </div>
                </article>

                <LegalLinks className="mt-8" />
            </main>
        </div>
    );
}

export function Section({
    title,
    children,
}: PropsWithChildren<{ title: string }>) {
    return (
        <section className="space-y-3">
            <h2 className="text-base font-semibold text-gray-900">{title}</h2>
            {children}
        </section>
    );
}

export function List({ children }: PropsWithChildren) {
    return <ul className="list-disc space-y-1 ps-5">{children}</ul>;
}
