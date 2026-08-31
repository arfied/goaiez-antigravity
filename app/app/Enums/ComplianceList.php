<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The external registers this application scrubs against
 * (`29` §2 rule 11, `24` §3.4).
 *
 * ⚠️ EACH CASE IS A DIFFERENT LEGAL INSTRUMENT WITH A DIFFERENT REACH, and
 * flattening them into one "blocked" list is the mistake this enum exists to
 * prevent. A litigator entry stops every message; a Do Not Call entry stops
 * marketing and leaves a review request alone; a reassignment stops only the
 * messages authorised by consent given *before* the number changed hands. One
 * boolean column would answer all three the same way, which is decision 286's
 * finding — `customers.is_suppressed` was lossy, not merely denormalised —
 * applied one layer out.
 *
 * ⚠️ THE DATA IS NOT HERE AND IS NOT OURS TO INVENT. Federal DNC access is a
 * subscription through telemarketing.donotcall.gov, the Reassigned Numbers
 * Database is an FCC-designated administrator, and litigator lists are
 * commercial products. `compliance_suppressions` ships **empty**, and every
 * marketing send is refused while it is — see `SuppressionRegistry::isLoaded()`.
 * That is the fail-closed direction and it costs nothing today, because `24`
 * §3.3 makes every message this product currently sends transactional.
 *
 * ⚠️ WHAT A NEW CASE COSTS. `appliesTo()` and `requiresEffectiveDate()` are both
 * exhaustive matches with no default, so a fifth register cannot inherit an
 * answer nobody chose. That is the same construction as `OptOutScope::
 * requiresBusiness()` and for the same reason: the wrong inherited answer here
 * is a message sent to somebody on a list we paid to be told about.
 */
enum ComplianceList: string
{
    /**
     * The federal Do Not Call registry.
     */
    case FederalDnc = 'federal_dnc';

    /**
     * A state Do Not Call registry. Several states run their own and a number
     * can be on one without being on the federal list.
     */
    case StateDnc = 'state_dnc';

    /**
     * Known TCPA litigators — people who make a living from being messaged.
     *
     * `24` §3.4 applies this to "all". Not a legal prohibition but a commercial
     * one, and the only entry here that blocks a transactional message.
     */
    case Litigator = 'litigator';

    /**
     * A number that has been permanently disconnected and reassigned.
     *
     * ⚠️ THE ONLY CASE WHERE THE DATE IS THE WHOLE POINT. The register does not
     * say "never message this number" — it says the number changed hands on a
     * date, so consent captured before that date was given by somebody else.
     * `24` §3.4 frames this as "any number not contacted in 30+ days", which is
     * one way to trigger the lookup; the rule the lookup answers is the one
     * implemented here, and it needs no last-contacted tracking because the
     * consent record already carries the date to compare against.
     */
    case ReassignedNumber = 'reassigned_number';

    /**
     * Which outreach purposes this register blocks.
     *
     * @return list<OutreachPurpose>
     */
    public function appliesTo(): array
    {
        return match ($this) {
            // `24` §3.4: "All". A person who sues for a living sues over a
            // review request as readily as over an offer, and the
            // existing-customer exemption is a defence rather than a shield
            // against being named.
            self::Litigator => [OutreachPurpose::Marketing, OutreachPurpose::Transactional],

            // `24` §3.4: "Marketing messages (existing-customer relationship
            // exempts most transactional)".
            self::FederalDnc, self::StateDnc => [OutreachPurpose::Marketing],

            // A reassignment invalidates the consent, and consent is what
            // authorises both purposes. Nothing survives it.
            self::ReassignedNumber => [OutreachPurpose::Marketing, OutreachPurpose::Transactional],
        };
    }

    /**
     * Whether a row in this register is meaningless without a date.
     *
     * Enforced by a database CHECK as well as here — slice B's three-layer
     * precedent (314–316), for a rule whose failure is either a message to
     * somebody whose number was reassigned, or every customer of a reassigned
     * number blocked forever.
     */
    public function requiresEffectiveDate(): bool
    {
        return match ($this) {
            self::ReassignedNumber => true,
            self::FederalDnc, self::StateDnc, self::Litigator => false,
        };
    }

    /**
     * Whether a row in this register names a state.
     */
    public function requiresState(): bool
    {
        return match ($this) {
            self::StateDnc => true,
            self::FederalDnc, self::Litigator, self::ReassignedNumber => false,
        };
    }
}
