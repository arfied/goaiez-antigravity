<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\SecondFactor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Where a person turns on a second factor (`28` §9.1).
 *
 * ## Why this screen exists at all
 *
 * `fortify.views` is false, so Fortify ships no enrolment UI, and its POST
 * endpoints were additionally unreachable behind a `password.confirm` route
 * that did not exist (660). **Mandating 2FA without building this first would
 * have locked every internal account out of the application on deploy** —
 * `RequiresTwoFactor` refuses the console, and the only way to satisfy it would
 * have been a route nobody could reach.
 *
 * ⚠️ **It is deliberately exempt from `RequiresTwoFactor`**, and that exemption
 * is what keeps the middleware from being a trap. See the middleware for the
 * whole list and why each entry is on it.
 *
 * ## Three states, and the middle one is the one that gets forgotten
 *
 * A secret can exist while the factor does not work — somebody pressed "Turn
 * on" and closed the tab — and with `confirm => true` that person does **not**
 * have 2FA. A screen modelling two states sends them back to the start and
 * mints a second secret, orphaning the one they may already have scanned. All
 * three questions are asked of `SecondFactor`, which is also the only thing in
 * `app/` that reads the two columns.
 *
 * ## No XHR, and the QR renders server-side
 *
 * Fortify offers `GET /user/two-factor-qr-code` and `GET
 * /user/two-factor-secret-key` as JSON for a client to fetch. This screen calls
 * `twoFactorQrCodeSvg()` on the model and prints the SVG into the page instead,
 * so enrolment completes with **scripting off** — the standard decision 387
 * held the feedback picker to, applied to the one screen an internal account
 * cannot work without. It also needs no new dependency: `bacon/bacon-qr-code`
 * and `pragmarx/google2fa` arrive with Fortify.
 *
 * ## The secret key is printed beside the QR, on purpose
 *
 * A QR code is unreadable to a screen-reader user and unusable to somebody
 * enrolling on the same device that displays it. 2FA is mandatory for staff, so
 * a person who cannot scan would have no way into the application at all — the
 * typed key is not a convenience here, it is the accessible path (WCAG 2.2 AA,
 * `22`).
 */
final class TwoFactorSetupController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        assert($user instanceof User);

        $held = SecondFactor::held($user);
        $enrolling = SecondFactor::enrolling($user);

        return view('auth.two-factor-setup', [
            'started' => $held || $enrolling,
            'confirmed' => $held,
            // twoFactorQrCodeSvg() builds its URL from a secret, so it is asked
            // only while one exists and enrolment is unfinished.
            'qrCodeSvg' => $enrolling ? $user->twoFactorQrCodeSvg() : null,
            'secretKey' => SecondFactor::enrolmentKey($user),
            // Shown once enrolment is complete, and never before: a recovery
            // code handed out beside an unconfirmed secret is a credential for a
            // factor that may never be finished. recoveryCodes() also decrypts a
            // column that is null until the first enable.
            'recoveryCodes' => $held ? $user->recoveryCodes() : [],
        ]);
    }
}
