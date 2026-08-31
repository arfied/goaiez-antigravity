<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a business accepted the signup terms (T176 P22).
 *
 * ⚠️ **THE TWO ARE NOT EQUALLY STRONG AND THE COLUMN EXISTS TO SAY SO.** A
 * ticked box is a deliberate affirmative act with a `checkbox_state` in its
 * proof; continuing through a single sign-on button is acceptance of a notice
 * rendered beside that button. Both are recorded, both name the exact document
 * versions, and a reader can tell them apart — which is the honest arrangement,
 * and better than recording the weaker one as though it were the stronger.
 *
 * A string column cast to this enum, never a database enum (`CLAUDE.md`).
 */
enum TermsAcceptanceMethod: string
{
    case Checkbox = 'checkbox';
    case SsoContinue = 'sso_continue';

    /**
     * The wording the person was shown, verbatim.
     *
     * ⚠️ **HERE RATHER THAN IN THE TEMPLATE**, for `ConsentDisclosure`'s reason:
     * the words rendered on the page and the words stored in the proof blob have
     * to be the same words, and two copies are two things that drift. The views
     * render this and `TermsAcceptances` stores it, so a change reaches both.
     *
     * ⚠️ **IT NAMES ALL THREE DOCUMENTS BY THEIR OWN TITLES.** A lint asserts
     * that every document in {@see App\Services\Legal\SignupTerms::DOCUMENTS}
     * appears here, so adding a fourth cannot leave the sentence behind — which
     * would mean recording an acceptance of a document nobody was told about.
     *
     * A `match` with no default, so a third case forces an answer rather than
     * inheriting one.
     */
    public function notice(): string
    {
        return match ($this) {
            self::Checkbox => 'I agree to the Terms of Service and the SMS & Communications Terms, '
                .'and I have read the Privacy Policy.',
            self::SsoContinue => 'By continuing you agree to the Terms of Service and the '
                .'SMS & Communications Terms, and confirm you have read the Privacy Policy.',
        };
    }
}
