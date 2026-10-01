<?php

namespace App\Enums;

enum ConsentType: string
{
    case TermsOfService = 'terms';
    case PrivacyPolicy = 'privacy';
    case SmsMessaging = 'sms';

    /** The config/legal.php version key for this document. */
    public function versionKey(): string
    {
        return $this->value;
    }
}
