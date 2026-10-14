import TermsAgreement from '@/Components/TermsAgreement';
import { Link } from '@inertiajs/react';

export interface SubscriptionTermsProps {
    /** Whether this person has already agreed to the current Terms. */
    accepted: boolean;
    /** The server's exact wording (App\Support\Consent::AGREEMENT). */
    text: string;
}

/**
 * Under a purchase confirmation. Always links the Terms (price changes
 * included); asks for the tick - and only then lets the purchase go through -
 * when the person hasn't agreed to the current version yet. The server
 * enforces the same rule, so this is the form of it, not the guarantee.
 */
export default function SubscriptionTerms({
    terms,
    checked,
    onChange,
    error,
}: {
    terms: SubscriptionTermsProps;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
}) {
    if (!terms.accepted) {
        return (
            <TermsAgreement
                checked={checked}
                onChange={onChange}
                text={terms.text}
                error={error}
            />
        );
    }

    return (
        <p className="text-xs text-gray-500">
            By confirming you agree to the{' '}
            <Link
                href={route('legal.terms')}
                target="_blank"
                className="font-medium underline hover:text-foreground"
            >
                Terms of Service
            </Link>
            , including how prices can change.
        </p>
    );
}
