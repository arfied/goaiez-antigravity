<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\TermsAcceptanceMethod;
use App\Http\Controllers\Controller;
use App\Services\Legal\SignupTerms;
use Illuminate\Contracts\View\View;

/**
 * The sign-in page (`29` §7.1) — a controller only because it has to read
 * something (T176 P22).
 *
 * ⚠️ **IT WAS A `Route::view` AND THAT IS EXACTLY WHY IT MOVED**, on decision
 * 691's precedent: `/start` promised "Seven days free" for weeks because a
 * `Route::view` has nowhere to read a registry key from. This page carries the
 * **second signup door** — "Continue with Google" creates an account and a
 * tenant on a first sign-in — so it has to render the terms notice beside those
 * buttons and link the exact documents that are published, and a view route
 * cannot resolve either.
 *
 * ⚠️ **THE BUTTONS ARE UNCONDITIONAL AND THE NOTICE IS NOT.** Withholding the
 * providers when no terms are published would lock **existing** SSO customers
 * out of their own accounts over paperwork that has nothing to do with signing
 * in. What the unpublished state refuses is opening a *new* account, and that
 * refusal lives on the callback where the account would be created.
 */
final class LoginPageController extends Controller
{
    public function __invoke(SignupTerms $terms): View
    {
        return view('auth.login', [
            // The same string `TermsAcceptances` stores in the proof blob, so
            // the page and the record cannot disagree about what was shown.
            'termsNotice' => TermsAcceptanceMethod::SsoContinue->notice(),

            // Empty until counsel's text is published, which is also when the
            // callback refuses to open an account — a notice pointing at
            // documents nobody can read would be worse than none.
            'termsLinks' => $terms->links(),
        ]);
    }
}
