<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\Mail\ClassifiesUnderCanSpam;

/**
 * Which of CAN-SPAM's two message classes an email falls into — T176 P21.
 *
 * 15 U.S.C. §7702 draws exactly one line and hangs three obligations off it. A
 * **commercial electronic mail message** — §7702(2)(A), *"the primary purpose of
 * which is the commercial advertisement or promotion of a commercial product or
 * service"* — must carry a working opt-out mechanism (§7704(a)(3)), must honour
 * an opt-out within ten business days (§7704(a)(4)), and must state *"a valid
 * physical postal address of the sender"* (§7704(a)(5)(A)(iii)). A
 * **transactional or relationship message** — §7702(17) — carries none of those
 * three and must simply not lie about who it is from.
 *
 * ⛔ **THIS IS NOT `OutreachPurpose`, AND THE TWO WILL DISAGREE ABOUT THE SAME
 * MESSAGE.** `OutreachPurpose::Transactional` answers a **TCPA** question — may
 * this be sent without prior express *written* consent — and `ReviewInviteEmail`
 * is `Transactional` under it, on `24` §3.3's reasoning that a review request
 * stays transactional while it carries no offer and no incentive. That same
 * message is {@see self::Commercial} here, because CAN-SPAM's test is *primary
 * purpose* rather than *carries an offer*, and asking a stranger to publish a
 * public endorsement of a business is promotion of that business's service.
 * **Two statutes, two definitions, and the safe direction differs**: TCPA's safe
 * direction is to claim less, CAN-SPAM's is to give more. Collapsing them into
 * one axis would force one of the two into the wrong answer, and the one that
 * would move is the TCPA lane, which is the one that decides whether a message
 * may be sent at all.
 *
 * ⚠️ **OVER-APPLYING THE COMMERCIAL CLASS IS NOT FREE, WHICH IS WHY THIS IS A
 * DECISION PER NOTIFICATION AND NOT A DEFAULT.** An unsubscribe link on a
 * sign-in email, a receipt or a data export teaches the recipient to opt out of
 * the messages they most need, and the opt-out would then be honoured. So the
 * classification is declared on each notification through
 * {@see ClassifiesUnderCanSpam}, a lint in
 * `tests/Feature/Architecture/MailTest.php` fails the build when one does not
 * declare it, and the argument for each sits in that class's own docblock.
 */
enum CanSpamClass: string
{
    /**
     * §7702(2)(A). Carries the opt-out, the headers and the postal address.
     *
     * ⛔ **AND IT CANNOT BE SENT TO AN ACCOUNT HOLDER TODAY, BY CONSTRUCTION.**
     * The only suppression store this application has is
     * `ConsentService`'s, which is keyed on a tenant's **customer**; there is
     * no equivalent for a `User`. So a commercial notification sent through
     * `PlatformMailer::send()` — the account-holder path, which takes no
     * permit — would carry an unsubscribe link with nowhere to write, and
     * `PlatformMailer::deliverNow()` refuses it rather than sending an opt-out
     * that silently does nothing. The refusal is the mechanism; this sentence
     * is only the explanation.
     */
    case Commercial = 'commercial';

    /**
     * §7702(17). A message about a transaction, an account or an existing
     * relationship: a sign-in link, a receipt, a renewal notice, a reply to a
     * support request, a file somebody asked for.
     *
     * ⚠️ **NAMED FOR THE STATUTE RATHER THAN SHORTENED TO `Transactional`**,
     * so it cannot be read as `OutreachPurpose::Transactional` by somebody
     * scanning a `match`. The two answer different questions and the
     * collision would be silent.
     */
    case TransactionalOrRelationship = 'transactional_or_relationship';

    /**
     * Whether this class owes the recipient an opt-out and the sender's postal
     * address.
     *
     * A `match` with no default, so a third case cannot inherit an answer
     * nobody chose — `SuppressionReason::isLiftable()`'s rule, on a method
     * whose wrong answer is either a statutory violation or an unsubscribe
     * link on somebody's password reset.
     */
    public function owesOptOut(): bool
    {
        return match ($this) {
            self::Commercial => true,
            self::TransactionalOrRelationship => false,
        };
    }
}
