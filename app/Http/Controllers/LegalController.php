<?php

namespace App\Http\Controllers;

use App\Support\Consent;
use App\Support\PageMeta;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public legal pages - reachable signed out, from the marketing page,
 * sign-in/registration, emails and the app footer. Wording lives in the
 * Legal/* pages; who it's "from" and the version/date come from
 * config/legal.php.
 */
class LegalController extends Controller
{
    public function terms(): Response
    {
        return $this->page('Legal/Terms', 'terms', 'Terms of Service', 'The terms for using the '.config('app.name').' quoting, job-tracking and invoicing service.');
    }

    public function privacy(): Response
    {
        return $this->page('Legal/Privacy', 'privacy', 'Privacy Policy', 'How '.config('app.name').' collects, uses and protects information about shops, their teams and their customers.');
    }

    public function sms(): Response
    {
        return $this->page('Legal/Sms', 'sms', 'SMS Terms', 'How '.config('app.name').' sends text messages on behalf of shops, and how to opt out or get help.');
    }

    private function page(string $component, string $document, string $title, string $description): Response
    {
        return Inertia::render($component, [
            'company' => config('legal.company'),
            'sms' => config('legal.sms'),
            'appName' => config('app.name'),
            'effectiveDate' => config("legal.versions.{$document}"),
            // The exact checkbox wording, so the SMS page can never differ from the form.
            'smsOptInText' => Consent::smsOptInText(),
            // The notice period the Terms promise for a price increase - the
            // same number the admin's tier form proposes, so they can't differ.
            'priceNoticeDays' => (int) config('shop.price_increase_notice_days'),
        ])->withViewData(['seo' => new PageMeta($title, $description)]);
    }
}
