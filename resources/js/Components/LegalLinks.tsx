import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';

/**
 * Terms / Privacy / SMS Terms links - the one place the public legal pages
 * are linked from (marketing page, sign-in and registration, the app footer,
 * onboarding), so they're reachable from everywhere a person might look.
 * Renders nothing on a self-hosted install: they are our service's terms.
 */
export default function LegalLinks({ className = '' }: { className?: string }) {
    const { selfHosted } = usePage<PageProps>().props;

    if (selfHosted) {
        return null;
    }

    const link =
        'underline-offset-2 hover:text-foreground hover:underline focus:outline-hidden focus:underline';

    return (
        <nav
            aria-label="Legal"
            className={`flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-gray-500 ${className}`}
        >
            <Link href={route('legal.terms')} className={link}>
                Terms of Service
            </Link>
            <Link href={route('legal.privacy')} className={link}>
                Privacy Policy
            </Link>
            <Link href={route('legal.sms')} className={link}>
                SMS Terms
            </Link>
        </nav>
    );
}
