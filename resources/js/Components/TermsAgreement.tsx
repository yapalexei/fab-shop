import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import { Link } from '@inertiajs/react';

/**
 * The required "I agree to the Terms and Privacy Policy" checkbox on every
 * form that creates an account. `text` is the server's wording
 * (App\Support\Consent::AGREEMENT) - the same string it stores with the
 * consent record - so what's shown is exactly what's on file. Never
 * pre-checked.
 */
export default function TermsAgreement({
    checked,
    onChange,
    text,
    error,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    text: string;
    error?: string;
}) {
    const link = 'font-medium underline hover:text-gray-900';

    return (
        <div>
            <label className="flex items-start gap-2 text-sm text-gray-600">
                <Checkbox
                    name="accepted_terms"
                    checked={checked}
                    onChange={(e) => onChange(e.target.checked)}
                    className="mt-0.5"
                    required
                />
                <span>
                    {text}{' '}
                    <span className="text-gray-500">
                        (Read the{' '}
                        <Link
                            href={route('legal.terms')}
                            target="_blank"
                            className={link}
                        >
                            Terms of Service
                        </Link>{' '}
                        and{' '}
                        <Link
                            href={route('legal.privacy')}
                            target="_blank"
                            className={link}
                        >
                            Privacy Policy
                        </Link>
                        .)
                    </span>
                </span>
            </label>
            <InputError message={error} className="mt-2" />
        </div>
    );
}
