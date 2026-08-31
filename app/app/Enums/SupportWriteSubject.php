<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Impersonation\Impersonation;
use App\Services\Knowledge\KnowledgeUploads;
use App\Services\Reviews\ResponseTemplates;
use App\Services\Reviews\ReviewGating;
use App\Services\Tenant\LocationTimezone;

/**
 * The `{thing}` in `28` §9.4's sentence — *"GO AI EZ support updated {thing}
 * for you ({ticket ref})"*.
 *
 * ## Why this enum exists at all
 *
 * ⛔ **IT REPLACES THE LAST FREE-TEXT TITLE SOURCE ON THE PLATFORM** (6649).
 * {@see Impersonation::recordWrite()} took a `string $describedToOwner` and
 * interpolated it straight into `activity_feed.title`, which
 * `Livewire\Account\Activity` prints **verbatim** on an **append-only** table.
 * Nothing validated it, and what kept a customer's name off an owner's screen
 * was that the method had no caller — a habit, and 272's shape besides.
 *
 * ⚠️ **AN ALLOWLIST, NEVER A SCRUBBER, AND THAT IS 6643's RULING RATHER THAN A
 * PREFERENCE.** A regex hunting names and numbers in a title fails open on the
 * exact example the old docblock gave — a first name is not detectable — and
 * reads as protection, which is 314–316's shape. A closed vocabulary cannot
 * carry a character the caller chose, so nothing here inspects a string.
 *
 * ⚠️ **6645 NAMED THIS FIX AND JUDGED IT TOO EXPENSIVE, AND THE JUDGMENT WAS
 * ABOUT A DIFFERENT COUNT.** *"The stronger close … is to type the parameter so
 * a title cannot be a bare string at all. That is eleven call sites across five
 * owners' files."* This one has **zero** call sites, so the cost is nil and the
 * trade flips. It is the only one of the four permitted title writers where it
 * does.
 *
 * ## The audit action and the owner's words are one fact, not two
 *
 * `recordWrite()` used to take both separately, so a write could be audited as
 * `location.updated` and described to the owner as something else entirely, and
 * neither record would say which was true. They travel together here for the
 * same reason `RatingSummary` carries its own denominator: two spellings of one
 * fact, kept apart, are two spellings that eventually disagree.
 *
 * ## What a case may name, and the lint that enforces it
 *
 * ⛔ **A CASE MAY NEVER DESCRIBE SOMETHING `28` §9.4's BLOCKLIST REFUSES.**
 * {@see ImpersonationCapability} is a blocklist of acts support may not perform
 * in an act-as session; a subject here is a sentence saying support *did*
 * perform one. A `PaymentMethod` case would let the feed tell an owner support
 * changed their card — which `ImpersonationCapability::ManagePaymentMethods`
 * exists to make impossible, and which SAQ-A depends on being impossible.
 * `ImpersonationTest` fails the build on any case whose subject collides with a
 * blocklisted capability, and that assertion is non-vacuous today.
 *
 * ⚠️ **DECLARED AHEAD OF ITS CALL SITES, AND THAT IS A CONFESSION RATHER THAN A
 * DESIGN** — the same one {@see ImpersonationCapability::reachableToday()}
 * makes, pointing the opposite way. A blocklist case with no call site **fails
 * closed**: it refuses an act nobody has built yet. A case *here* fails **open**
 * in the sense that matters least and open in one that matters: it permits a
 * sentence nobody has written yet, so a long speculative list would be
 * decoration — 272's shape inside the fix for 272. **Each case below therefore
 * names a service in `app/` that writes it today**, cited by `{@see}`, and the
 * bar for a fifth is a caller rather than an idea.
 *
 * ⛔ **"NONE OF THE FOUR HAS A CALLER YET" WAS TRUE AND IS NOT — CORRECTED
 * WAVE 38 (10630).** {@see Impersonation::recordWrite()}
 * itself had zero callers, not merely this enum: `impersonation_sessions.writes`
 * was permanently 0, `StaffActivity` rendered that 0 to staff as fact, and
 * `SupportSessionSummary` emailed an owner a count of 0 against an empty feed
 * on every act-as session that ever made a change. **All four writers now call
 * it** — `ReviewGating::writeGatingRow()`, `LocationTimezone::set()`,
 * `ResponseTemplates::add()`/`remove()`, `KnowledgeUploads::accept()` — gated
 * on {@see Impersonation::current()} returning a
 * session, which is null on every ordinary owner write and can only be
 * non-null here because the write already survived the read-only connection
 * a view-only session sits behind. The bar for a fifth case is unchanged: a
 * caller, not an idea.
 */
enum SupportWriteSubject: string
{
    /**
     * Who gets asked to leave a public review, and at what rating.
     *
     * ⛔ **THE `{@see}` NAMED A METHOD THAT DOES NOT EXIST — CORRECTED WAVE 38
     * (10630).** It read `ReviewGating::apply()`, which this class has never
     * declared; found while wiring the writer rather than by a separate
     * audit.
     *
     * @see ReviewGating::inviteEveryone() — a writer, reached from `Account\ReviewRules`.
     * @see ReviewGating::gateAt() — the other writer, same screen.
     */
    case ReviewInviteRules = 'review_invite_rules';

    /**
     * The location's time zone — what every scheduled send is timed against.
     *
     * @see LocationTimezone::set() — the writer, reached from `Account\Settings`.
     */
    case LocationTimeZone = 'location_time_zone';

    /**
     * The examples an owner's review replies are written from.
     *
     * @see ResponseTemplates::add() — the writer, reached from `Account\ReplyExamples`.
     */
    case ReplyExamples = 'reply_examples';

    /**
     * The documents the assistant answers a customer's questions from.
     *
     * @see KnowledgeUploads::accept() — the writer, reached from `Account\Knowledge`.
     */
    case AssistantKnowledge = 'assistant_knowledge';

    /**
     * What the auditor reads.
     *
     * The same vocabulary `AuditService` is given everywhere else — a dotted
     * `entity.verb` — so a reader grepping `audit_log` for a table's history
     * finds a support write beside an owner's own.
     */
    public function auditAction(): string
    {
        return match ($this) {
            // ⚠️ **THE PREFIXES ARE THE ONES THOSE SERVICES ALREADY WRITE**,
            // checked against the literal in each file rather than guessed:
            // `review_gating.everyone_invited` / `.threshold_set`,
            // `location.timezone_set`, `response_template.added` / `.removed`.
            // An auditor asking *"who has touched this?"* greps one prefix and
            // finds the owner's own change beside support's.
            self::ReviewInviteRules => 'review_gating.updated',
            // ⚠️ **THE SAME STRING, NOT A NEIGHBOURING ONE.** `LocationTimezone`
            // writes exactly this, and a time zone has one writer's worth of
            // history; splitting support's out would hide it from the grep that
            // finds the rest.
            self::LocationTimeZone => 'location.timezone_set',
            self::ReplyExamples => 'response_template.updated',
            // ⚠️ **THE ONE WITH NO PREFIX TO MATCH**: `KnowledgeUploads` writes
            // no audit entry at all today, so this follows `response_template`'s
            // singular convention rather than inventing a second one.
            self::AssistantKnowledge => 'knowledge_source.updated',
        };
    }

    /**
     * What the owner reads, in `28` §9.4's sentence.
     *
     * ⚠️ **A VERB PHRASE, NOT A NOUN**, because it is slotted between *"GO AI EZ
     * support"* and *"for you"* — `22`'s rule that a string names what the
     * person controls, applied to a sentence assembled in two places. Read the
     * whole line before changing one of these: *"GO AI EZ support updated who
     * gets asked to leave you a review for you (SUP-1042)"* is what a bad noun
     * produces.
     */
    public function describedToOwner(): string
    {
        return match ($this) {
            self::ReviewInviteRules => 'updated the review rules',
            self::LocationTimeZone => 'corrected the time zone',
            self::ReplyExamples => 'updated the reply examples',
            self::AssistantKnowledge => 'updated the assistant’s documents',
        };
    }
}
