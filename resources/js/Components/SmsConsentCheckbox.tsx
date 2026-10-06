import { Checkbox } from '@/Components/ui/checkbox';
import { Link } from '@inertiajs/react';

/**
 * Opt-in for text messages, for whichever form first collects a mobile
 * number (none does yet - this is ready for when one does).
 *
 * Carrier (A2P 10DLC / CTIA) rules this is built around:
 * - It is its own checkbox, never bundled with the Terms agreement.
 * - It is never pre-checked, and ticking it is optional - the form must
 *   still submit without it, since consent can't be a condition of purchase.
 * - `text` is the server's wording (App\Support\Consent::smsOptInText()),
 *   the same string stored in the consent record.
 * - Pair it with Consent::recordSms() on submit so the opt-in is on file.
 */
export default function SmsConsentCheckbox({
    checked,
    onChange,
    text,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    text: string;
}) {
    const link = 'font-medium underline hover:text-foreground';

    return (
        <label className="flex items-start gap-2 text-sm text-gray-600">
            <Checkbox
                name="sms_consent"
                checked={checked}
                onCheckedChange={(v) => onChange(v === true)}
                className="mt-0.5"
            />
            <span>
                {text}{' '}
                <span className="text-gray-500">
                    See our{' '}
                    <Link
                        href={route('legal.sms')}
                        target="_blank"
                        className={link}
                    >
                        SMS Terms
                    </Link>{' '}
                    and{' '}
                    <Link
                        href={route('legal.privacy')}
                        target="_blank"
                        className={link}
                    >
                        Privacy Policy
                    </Link>
                    .
                </span>
            </span>
        </label>
    );
}
