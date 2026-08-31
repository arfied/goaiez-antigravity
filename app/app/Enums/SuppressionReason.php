<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why an identifier is suppressed — and therefore what, if anything, lifts it.
 *
 * ⚠️ FOUR CASES BECAUSE THE LIFT LAW DIFFERS, NOT BECAUSE ANYBODY WANTED
 * CATEGORIES. `suppression_list.reason` was free text and every row in it was a
 * STOP, which was true right up until row 4 gains a bounce feed and a complaint
 * webhook — and at that point one column holding three facts is decision 286's
 * `customers.is_suppressed` rebuilt: a single value that cannot say "refused to
 * hear from us" and "this address does not exist" at the same time, so the code
 * lifting one lifts the other.
 *
 * `BUILD-PLAN` §3.1 makes the identical argument about line type in the sentence
 * it uses to refuse a fifth `ComplianceList` case: *"It is an attribute of a
 * number, not an instrument against it."* A bounce is an attribute. A STOP is an
 * instrument. Flattening them is how a hard bounce becomes a legal refusal, and
 * how a legal refusal becomes something a delivery probe can clear.
 *
 * ⚠️ IT IS NOT A `ComplianceList` CASE AND MUST NEVER BECOME ONE, for the same
 * reason. That enum is `29` §2 rule 11's registers — DNC, reassigned numbers,
 * litigator — each of which says *do not contact* about a person. These say why
 * one identifier stopped working, which is a different question with a different
 * remedy.
 *
 * ⚠️ THERE IS NO DEFAULT ANYWHERE. `suppress()` takes one of these by requirement
 * (486's rule, applied to a parameter whose safe-looking default is the
 * permissive one): defaulting to `Stop` would classify a future bounce as a
 * refusal the customer never made, and defaulting to `Complaint` would make every
 * ordinary STOP permanently unliftable.
 */
enum SuppressionReason: string
{
    /**
     * The person told us to stop — a carrier STOP keyword, an unsubscribe click,
     * or a withdrawal recorded on their behalf with an actor.
     *
     * The only case that lifts, and carrier rules require that it can: honouring
     * START is not optional, which is why `ConsentService::suppress()` has named
     * this slice as row 4's since the day it was written.
     */
    case Stop = 'stop';

    /**
     * They reported us as spam.
     *
     * ⚠️ NEVER LIFTS, BY ANY PATH IN THIS SERVICE — not on a START, not on an
     * operator action, not on a fresh consent capture. A complaint is a statement
     * made to somebody else about us, and the mailbox provider acted on it; we
     * are not a party to it and have nothing to reverse. Un-suppressing here
     * would be re-mailing the one person whose provider has already been told
     * we are unwanted, which is how a sending domain's reputation ends.
     */
    case Complaint = 'complaint';

    /**
     * The address or number does not accept delivery.
     *
     * ⚠️ NOT A REFUSAL, AND STILL UNLIFTABLE TODAY. Nothing here is a statement
     * by the person — it is a fact about the identifier, and it can stop being
     * true (a mailbox over quota, a number reconnected). What lifts it is a
     * *verified re-delivery*, and this application has no delivery feed at all:
     * open question H, and the reason `review_invite.email_enabled` seeds false.
     *
     * So it is representable and refused, rather than absent. Absent would mean
     * row 4's bounce ingestion arriving with nowhere to put a bounce and reaching
     * for `Stop`, which is the flattening this enum exists to prevent.
     */
    case Bounce = 'bounce';

    /**
     * The business owner marked the contact **Never contact** (`34` §1.2).
     *
     * ⚠️ THE BUSINESS SAID IT, NOT THE PERSON, AND THAT IS THE ONLY REASON THIS
     * IS NOT `Stop`. Recording an owner's instruction as a STOP would put a
     * statement we authored — *the customer asked us to stop* — into the one
     * table whose whole job is to be true, and `suppress()` files it to
     * `audit_log` as `consent.withdrawn`, so the fabrication would outlive
     * everybody who could correct it. Decision 552's reasoning exactly, on the
     * other side of the consent boundary: `TenantAttested` exists because an
     * imported list is a tenant's claim *about* somebody, and this is a tenant's
     * instruction *about* somebody.
     *
     * ⚠️ IT IS TENANT-SCOPED AND MUST STAY SO. The owner is speaking about their
     * own relationship with that person; a dentist saying "never text this one"
     * says nothing about the mechanic, and writing it at `OptOutScope::Platform`
     * would let one tenant silently mute a person for every other tenant on the
     * shared number. That is the inverse of decision 294's hazard and does the
     * same kind of damage in the opposite direction.
     *
     * It is not a `ComplianceList` case either, for that enum's own stated
     * reason: rule 11's registers are statutes and industry lists that hold
     * against everybody. This holds against one business because that business
     * asked.
     */
    case NeverContact = 'never_contact';

    /**
     * Why a refusal was recorded, in the words an operator would use to a
     * carrier — wave 40 lane C, decision 10880.
     *
     * ⚠️ **IT LIVES HERE RATHER THAN ON THE SCREEN THAT RENDERS IT, AND THE
     * REASON IS A LINT.** `CrmTest`'s *"only the never-contact service records
     * a suppression as the owner instruction"* forbids any file outside this
     * one and `Services/Crm/NeverContact.php` from writing
     * `SuppressionReason::NeverContact` — so a `match` over every case cannot
     * be written in a Livewire component. That lint's subject is a WRITER
     * choosing the class; a read-side rendering is a different act and its
     * regex cannot tell them apart, so the answer is to put the sentence where
     * the case already lives rather than to widen the allowlist and make a
     * screen look permitted to choose one.
     *
     * ⚠️ **`Bounce` IS WORDED FOR BOTH CHANNELS**, because this enum is shared:
     * a bounce is an email fact today and the column it lands in carries SMS
     * refusals too.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::Stop => 'They asked us to stop.',
            self::Complaint => 'They reported a message as spam.',
            self::Bounce => 'Messages to them kept failing.',
            self::NeverContact => 'A business asked us never to contact them.',
        };
    }

    /**
     * Whether `ConsentService::lift()` may clear a suppression of this class.
     *
     * A match rather than a comparison so a fourth case cannot inherit an answer
     * nobody chose — `OptOutScope::requiresBusiness()`'s reasoning, on a method
     * whose wrong answer is a message to somebody who reported us as spam.
     *
     * ⚠️ `NeverContact` LIFTS, AND IT IS THE ONLY ONE THAT LIFTS FOR THIS REASON:
     * it is the owner's own instruction about their own list, so the owner
     * withdrawing it restores a permission nobody else ever removed. `Stop`
     * lifts because carrier rules require START to be honoured; these two are
     * liftable on entirely different authorities and a reader should not take
     * one for the other.
     */
    public function isLiftable(): bool
    {
        return match ($this) {
            self::Stop, self::NeverContact => true,
            self::Complaint, self::Bounce => false,
        };
    }

    /**
     * Why a lift was refused, in words an operator can act on.
     *
     * The two refusals are not the same fact and must not read as one: a
     * complaint is permanent and the operator should stop looking, a bounce is
     * waiting on a mechanism that does not exist yet. One message for both would
     * send somebody hunting for a feature in the first case and make them give up
     * in the second.
     */
    public function liftRefusal(): ?string
    {
        return match ($this) {
            self::Stop, self::NeverContact => null,
            self::Complaint => 'A complaint never lifts. The mailbox provider was told we are unwanted '
                .'and we are not a party to that; there is nothing here to reverse.',
            self::Bounce => 'A bounce lifts on a verified re-delivery, and no delivery feed exists yet '
                .'(open question H). Suppression stands until one does.',
        };
    }
}
