<?php

namespace App\Http\Controllers;

use App\Support\Quackback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mints the ssoToken that identifies the signed-in user to the feedback
 * widget. Deliberately outside the `auth` middleware: a visitor who isn't
 * signed in gets a plain 401 (the page then simply stays anonymous) instead of
 * a redirect to the login page. The session is the only proof of who is
 * asking - the token is built from it, never from anything the browser sends.
 */
class QuackbackTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(Quackback::canIdentify(), 404);

        $user = $request->user();

        abort_if($user === null, 401);
        // AI agent users have no browser session, but never vouch for one.
        abort_if($user->is_agent, 403);

        return response()
            ->json(['ssoToken' => Quackback::ssoToken($user)])
            ->header('Cache-Control', 'no-store, private');
    }
}
