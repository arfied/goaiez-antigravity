<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a consent record was captured (DATA-MODEL §5.6).
 *
 * The split that matters is not alphabetical: FeedbackPage, Chat, Booking and Qr
 * are surfaces we render and can therefore prove — they carry a disclosure
 * version and a stored proof blob. Import, Pos, Call and Manual are assertions a
 * tenant makes about something that happened elsewhere, which is why they land
 * on Lane B and need the tenant's own brand registration.
 */
enum CaptureSurface: string
{
    case FeedbackPage = 'feedback_page';
    case Chat = 'chat';
    case Booking = 'booking';
    case Qr = 'qr';
    case Import = 'import';
    case Pos = 'pos';
    case Call = 'call';
    case Manual = 'manual';

    /**
     * The card form (2980–2999).
     *
     * ⚠️ **A SURFACE WE RENDER THAT IS NOT A MESSAGING SURFACE, AND IT IS THE
     * FIRST OF ITS KIND HERE.** It exists so that the auto-renewal
     * acknowledgment can reach `ConsentProof`'s three guards — url, ip_hash and
     * user_agent mandatory, no raw address at any depth, no pre-checked box —
     * without a second copy of them, which is 2933's whole reason for
     * extracting that class.
     *
     * ⛔ **NO `consent_records` ROW MAY EVER CARRY IT.** Nobody agrees to be
     * contacted on a checkout page, and a record claiming they did would be a
     * platform-captured Lane A basis manufactured out of a billing form.
     * `ConsentCapture` refuses it by name rather than by convention — a rule
     * this specific, written only in a docblock, is one the next caller does
     * not read.
     */
    case Checkout = 'checkout';

    /**
     * The signup form and the SSO buttons beside it (T176 P22).
     *
     * ⚠️ **THE SECOND SURFACE WE RENDER THAT IS NOT A MESSAGING SURFACE**, and
     * it exists for `Checkout`'s reason: the signup terms acceptance has to
     * reach `ConsentProof`'s three guards — url, ip_hash and user_agent
     * mandatory, no raw address at any depth, no pre-checked box — without a
     * second copy of them.
     *
     * ⛔ **NO `consent_records` ROW MAY EVER CARRY IT** (terms of service acceptance is not recipient marketing consent). A business
     * accepting our Terms is not a person agreeing to be contacted, and a
     * record claiming otherwise would manufacture a platform-captured Lane A
     * basis out of a signup form — for the *account holder*, who is not one of
     * their own customers at all. `ConsentCapture` refuses it by name.
     */
    case Signup = 'signup';

    /**
     * The setup wizard and the account settings screen, where an owner may
     * consent to be texted about their own account (10540, the owner ruling of
     * 2026-08-27).
     *
     * ⚠️ **THE THIRD SURFACE WE RENDER THAT IS NOT A MESSAGING-CONSENT
     * SURFACE, AND IT SHARES `Checkout`'s AND `Signup`'s REASON RATHER THAN
     * `FeedbackPage`'s.** It reaches `ConsentProof`'s three guards for the same
     * reason those two do — a page we render has the URL, the hashed address
     * and the user agent in hand — but the person on it is the **account
     * holder**, not a customer, exactly like the two cases above it.
     *
     * ⛔ **NO `consent_records` ROW MAY EVER CARRY IT**, for `Signup`'s reason
     * verbatim: the owner is not one of their own business's customers, and a
     * record claiming otherwise would manufacture a Lane A basis to contact
     * them as though they were. `App\Services\Consent\OwnerConsentService`
     * writes `owner_notification_consents` instead — a table with no
     * `customer_id` column at all, `terms_acceptances`' shape rather than
     * `consent_records`'. `ConsentCapture` refuses this case by name, the same
     * as the two above it.
     */
    case OwnerNotify = 'owner_notify';

    /**
     * Did this application render the moment of consent?
     *
     * The split the docblock above describes, as a method rather than as a
     * sentence — which is the difference between a rule and a hope. Two things
     * read it: `ConsentCapture` requires a full proof blob here and only here,
     * and it refuses to let `CapturedBy::Platform` sit on a surface we did not
     * render.
     *
     * That second one is the load-bearing case. `29` §2 says the platform only
     * sends where the platform *owns* the consent record, and Lane A is a single
     * shared toll-free number under our own brand (`25` §1.3 line 77). A record
     * claiming `platform` capture on an imported list would put a contact we
     * never spoke to on that shared number, under the "via GO AI EZ" disclosure,
     * on the strength of a spreadsheet.
     *
     * ⚠️ **`Checkout`, `Signup` AND `OwnerNotify` ARE TRUE HERE AND THAT IS THE
     * POINT OF THEM.** We serve those pages, so the URL, the hashed address
     * and the user agent are all in hand and their absence means somebody
     * skipped a step. None of the three reaches the second reader above,
     * because `ConsentCapture` refuses all three outright.
     */
    public function isSelfRendered(): bool
    {
        return match ($this) {
            self::FeedbackPage, self::Chat, self::Booking, self::Qr,
            self::Checkout, self::Signup, self::OwnerNotify => true,
            self::Import, self::Pos, self::Call, self::Manual => false,
        };
    }
}
