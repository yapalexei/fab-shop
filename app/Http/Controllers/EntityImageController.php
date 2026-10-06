<?php

namespace App\Http\Controllers;

use App\Models\BusinessPartner;
use App\Models\Customer;
use App\Models\Material;
use App\Support\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the pictures that belong to catalog and customer records. The
 * models arrive through route binding, so the tenant scope already refuses
 * another shop's records. A logo is shown wherever its owner's name is - on
 * quotes and jobs too - so seeing any of those pages is enough to load it.
 */
class EntityImageController extends Controller
{
    public function customerLogo(Request $request, Customer $customer): StreamedResponse
    {
        abort_unless(Gate::any(['customers.view', 'quotes.view', 'jobs.view']), 403);

        return Images::respond($request, $customer, 'logo');
    }

    public function materialImage(Request $request, Material $material): StreamedResponse
    {
        abort_unless(Gate::any(['materials.view', 'quotes.view', 'jobs.view']), 403);

        return Images::respond($request, $material, 'image');
    }

    public function businessPartnerLogo(Request $request, BusinessPartner $businessPartner): StreamedResponse
    {
        abort_unless(Gate::any(['business-partners.view', 'quotes.view']), 403);

        return Images::respond($request, $businessPartner, 'logo');
    }
}
