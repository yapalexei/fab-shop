<?php

namespace App\Http\Controllers;

use App\Support\Consent;
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
        return $this->page('Legal/Terms', 'terms');
    }

    public function privacy(): Response
    {
        return $this->page('Legal/Privacy', 'privacy');
    }

    public function sms(): Response
    {
        return $this->page('Legal/Sms', 'sms');
    }

    private function page(string $component, string $document): Response
    {
        return Inertia::render($component, [
            'company' => config('legal.company'),
            'sms' => config('legal.sms'),
            'appName' => config('app.name'),
            'effectiveDate' => config("legal.versions.{$document}"),
            // The exact checkbox wording, so the SMS page can never differ from the form.
            'smsOptInText' => Consent::smsOptInText(),
        ]);
    }
}
