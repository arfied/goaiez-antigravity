<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an outbound message travels (DATA-MODEL §5.1 `outreach_channel`).
 *
 * Also the channel a consent record and a suppression entry are scoped to —
 * consent to SMS says nothing about email.
 */
enum OutreachChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Whatsapp = 'whatsapp';

    /**
     * A phone call. Added wave 39 lane B (`CLAUDE.md` §Deliberate spec
     * overrides, decisions 9363/9364/9366/9367) — the gate this case makes
     * askable, not the ability to place one. `tests/Feature/Architecture/
     * VoiceTest.php` still refuses every path from this value to a vendor call:
     * no method on `VoiceProvider` can originate one, no voice file may issue a
     * mutating HTTP verb, and the outbound vocabulary stays denylisted.
     */
    case Voice = 'voice';

    /**
     * Whether this channel's identifier is a phone number.
     *
     * ⚠️ HERE RATHER THAN IN THE CALLER, AND THE ARCHITECTURE LINT IS WHY. "No
     * file outside the consent lane may name WhatsApp" is a real rule with a
     * real reason — offering the channel on an owner-facing surface is what it
     * prevents — and its own comment says a fourth file wanting onto the
     * allowlist is a finding rather than a false positive. `Identifier` wanted
     * on, to answer "does this channel normalise as a phone number".
     *
     * It does not need to know. **The fact belongs to the channel**, which is
     * already allowlisted because consent keys on it, and asking the enum leaves
     * the caller with no reason to name any case at all. The match stays
     * exhaustive with no default, so a fourth channel is a compile-time
     * conversation.
     *
     * `ConsentService::SMS_FAMILY` expresses the same fact for the messaging
     * lane and is deliberately left alone: it selects consent *records* for lane
     * derivation rather than answering a question about an identifier, and
     * collapsing the two would tie the lane to the identifier format.
     *
     * ✅ **`Voice` JOINS THE PHONE-SHAPED ARM, AND THIS IS THE FIFTH CASE THE
     * DOCBLOCK PROMISED WOULD BE A CONVERSATION.** A call rides a phone number
     * exactly as SMS and WhatsApp do — `24`'s calling-window statutes and 47 CFR
     * 64.1200 are written against a *telephone number*, not a messaging
     * program — so `Identifier::hash()` must fold it into the same normal form
     * or a number that said STOP over SMS would hash to a value a Voice check
     * never compares against (the finding this whole lane exists to close; see
     * `suppressionSharedWith()` below for the other half of the same fact).
     */
    public function usesPhoneIdentifier(): bool
    {
        return match ($this) {
            self::Sms, self::Whatsapp, self::Voice => true,
            self::Email => false,
        };
    }

    /**
     * Every channel whose suppression records also refuse a send on this one.
     *
     * ⚠️ **NOT `usesPhoneIdentifier()` REUSED, AND NOT `ConsentService::
     * SMS_FAMILY`.** Both already exist and neither answers this. Decision 427
     * kept `SMS_FAMILY` apart from the identifier-shape fact because it selects
     * consent *records* for lane derivation; this is a third, narrower fact
     * about *suppression* alone, and conflating any of the three is exactly the
     * mistake 427 refused.
     *
     * ⚠️ **`Sms` AND `Whatsapp` EACH ANSWER `[self]`, UNCHANGED, ON PURPOSE.**
     * `ConsentServiceTest`'s "suppression on one channel does not block a
     * different channel sharing the same identifier" is a deliberate, argued
     * design: a WhatsApp opt-out must not silently suppress SMS, because the two
     * are independent programs a person can withdraw from separately. **This
     * lane does not reopen that.**
     *
     * ⚠️ **`Voice` IS DIFFERENT IN KIND, NOT DEGREE, AND THAT DIFFERENCE IS WHY
     * IT WIDENS WHERE THE OTHER TWO DO NOT.** Nothing in this application
     * captures a Voice-specific consent, so nothing can ever write a Voice-typed
     * suppression row either — a bare `[self::Voice]` here would make every
     * Voice suppression check answer "not suppressed", always, which is the
     * exact vacuous gate this lane exists to refuse (`CLAUDE.md` §Critical
     * rules: *"the gate before the door is built and the door is not"*). Unlike
     * WhatsApp, a call is not an independent program
     * somebody opted into: it is the primitive act 47 CFR 64.1200 and the whole
     * Do Not Call apparatus were written to govern, and the concrete exposure a
     * wave-39 scout measured on this exact commit is a federal DNC extract or a
     * carrier STOP loaded under `--channel=sms` — the only channel this
     * platform has ever actually sent on — refusing nobody on a call to the
     * same number. So a Voice check also consults every row already recorded
     * for `Sms` and `Whatsapp`, the populated stores sharing its identifier;
     * `Sms`'s own check and `Whatsapp`'s stay exactly as narrow as they were.
     *
     * ⚠️ **READS WIDEN; WRITES AND CONSENT STAY EXACT.** `SuppressionRegistry::
     * load()`/`remove()`/`replace()` still file a register entry under the
     * literal channel an operator named, for provenance, and
     * `ConsentService::decide()`'s `ConsentRecord` lookup at step 4 stays an
     * exact `channel` match — permission is the one direction this method does
     * not touch, because granting is not the failure mode a refused STOP is.
     *
     * @return list<self>
     */
    public function suppressionSharedWith(): array
    {
        return match ($this) {
            self::Voice => [self::Voice, self::Sms, self::Whatsapp],
            self::Sms => [self::Sms],
            self::Whatsapp => [self::Whatsapp],
            self::Email => [self::Email],
        };
    }
}
