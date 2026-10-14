<?php

namespace App\Support;

use App\Enums\ConsentType;
use App\Models\ConsentRecord;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records what people agree to, and owns the exact wording shown next to
 * each checkbox so the stored text can never drift from what was on screen.
 * The same strings are sent to the frontend (see AGREEMENT/SMS_OPT_IN use in
 * the controllers), never retyped there.
 *
 * Changing any wording here is a material change: bump the matching version
 * in config/legal.php so new consents are recorded against the new text.
 */
final class Consent
{
    /** Shown beside the required checkbox at registration and invitation. */
    public const AGREEMENT = 'I agree to the Terms of Service and acknowledge the Privacy Policy.';

    public static function version(ConsentType $type): string
    {
        return config('legal.versions.'.$type->versionKey());
    }

    /**
     * Opt-in wording for text messages. Meets the usual carrier (A2P 10DLC /
     * CTIA) expectations: names the program and sender, says what kind of
     * messages, frequency, rates, how to stop and get help, that consent is
     * not a condition of purchase, and links the terms. It must be shown
     * beside an UNCHECKED, optional checkbox, separate from the Terms
     * agreement.
     */
    public static function smsOptInText(): string
    {
        $program = config('legal.sms.program_name');
        $app = config('app.name');

        return "I agree to receive text messages from {$program} ({$app}) at the mobile number I provided, such as account alerts and job or quote notifications. Message frequency varies. Message and data rates may apply. Reply STOP to cancel at any time, or HELP for help. Consent is not a condition of purchase.";
    }

    /**
     * Record that $user agreed to the Terms and Privacy Policy (two rows, one
     * per document, each with its own version).
     */
    public static function recordAgreement(User $user, string $source, Request $request, ?Shop $shop = null): void
    {
        foreach ([ConsentType::TermsOfService, ConsentType::PrivacyPolicy] as $type) {
            self::record($type, true, self::AGREEMENT, $source, $request, user: $user, shop: $shop);
        }
    }

    /**
     * Whether $user has agreed to the Terms of Service *as they read now*:
     * their most recent Terms consent is a grant recorded against the current
     * version. A person who agreed to an earlier version (or, before records
     * existed, to none) is asked again the next time they subscribe.
     */
    public static function hasAcceptedCurrentTerms(User $user): bool
    {
        $latest = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('type', ConsentType::TermsOfService)
            ->latest('id')
            ->first();

        return $latest !== null
            && $latest->granted
            && $latest->document_version === self::version(ConsentType::TermsOfService);
    }

    /**
     * Before anything that commits a shop to paying (a plan, an add-on, extra
     * seats): the person must have agreed to the current Terms. If they
     * haven't, the request has to carry the ticked `accepted_terms` box - the
     * same wording as at registration - and it is recorded on the spot. If
     * they have, nothing more is asked. Throws a validation error otherwise.
     */
    public static function requireAgreementToSubscribe(Request $request, User $user, ?Shop $shop = null): void
    {
        if (self::hasAcceptedCurrentTerms($user)) {
            return;
        }

        $request->validate(
            ['accepted_terms' => ['accepted']],
            ['accepted_terms.accepted' => 'You need to agree to the Terms of Service and Privacy Policy before you subscribe.'],
        );

        self::recordAgreement($user, 'subscription', $request, $shop);
    }

    /**
     * What the confirmation dialogs need: whether to ask, and the exact
     * wording to show beside the box.
     *
     * @return array{accepted: bool, text: string}
     */
    public static function subscriptionTerms(User $user): array
    {
        return ['accepted' => self::hasAcceptedCurrentTerms($user), 'text' => self::AGREEMENT];
    }

    /**
     * SMS opt-in (or opt-out, with $granted false) for a mobile number. Not
     * wired to any screen yet - call it from whichever form collects the
     * number, passing the exact text that form displayed.
     */
    public static function recordSms(string $phone, bool $granted, string $source, Request $request, ?User $user = null, ?Shop $shop = null, ?string $text = null): ConsentRecord
    {
        return self::record(
            ConsentType::SmsMessaging,
            $granted,
            $text ?? self::smsOptInText(),
            $source,
            $request,
            user: $user,
            shop: $shop,
            phone: $phone,
        );
    }

    /** Whether the most recent SMS record for this number is an opt-in. */
    public static function hasSmsConsent(string $phone): bool
    {
        return ConsentRecord::query()
            ->where('type', ConsentType::SmsMessaging)
            ->where('subject_phone', $phone)
            ->latest('id')
            ->value('granted') === true;
    }

    public static function record(ConsentType $type, bool $granted, string $text, string $source, Request $request, ?User $user = null, ?Shop $shop = null, ?string $phone = null): ConsentRecord
    {
        return ConsentRecord::query()->create([
            'user_id' => $user?->id,
            'shop_id' => $shop?->id ?? $user?->current_shop_id,
            'subject_email' => $user?->email,
            'subject_phone' => $phone,
            'type' => $type,
            'granted' => $granted,
            'document_version' => self::version($type),
            'consent_text' => $text,
            'source' => $source,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
