<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\ComplianceList;
use App\Enums\OptOutScope;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Models\OptOut;
use App\Models\SuppressionLift;
use App\Support\Identifier;
use Illuminate\Support\Collection;

/**
 * Everything this platform has on record against ONE phone number, read with
 * no tenant established — wave 40 lane C, decision 10880.
 *
 * ⛔ **A CARRIER COMPLAINT NAMES A NUMBER AND NOTHING ELSE, AND EVERY SCREEN
 * IN THIS CONSOLE WAS KEYED ON A BUSINESS.** `App\Livewire\Admin\
 * OwnerNotifyConsents` DESCRIBED itself as *"the answer to a carrier's 'this
 * number never agreed to be texted'"* — past tense since 10882, which corrected
 * that sentence in all four places it stood — and its lookup is still
 * `(int) trim($this->lookup)`, correctly, because it is the record for a
 * business you can already name. So the one question a carrier actually asks
 * could only be answered by hand, in `tinker`, against a table nothing in a
 * browser could reach. This is the read that starts from the number.
 *
 * ## ⛔ IT REPORTS THE RECORD. IT DOES NOT DECIDE A SEND.
 *
 * ⛔ **THE OBVIOUS DESIGN — "would we text this number today?" — WAS REFUSED,
 * AND THE REASON IS 8460.** A send decision is
 * {@see ConsentService::decide()}: it needs a tenant, a purpose, a
 * `ConsentRecord`, a daytime window and the tenant's own `suppression_list`,
 * and **none of those five is visible from a platform screen with no tenant
 * established.** A verdict computed here would be a second, weaker copy of the
 * send gate presented with the gate's authority — right until the day the two
 * disagreed, and then wrong in the direction that says *"we would not have
 * texted them"* about a message that went out. **So this returns rows, and the
 * screen says whose answer they are.**
 *
 * ⚠️ **THE ONE VERDICT IT DOES CARRY IS BORROWED, NEVER RECOMPUTED.**
 * `registerRefusals()` is {@see SuppressionRegistry::refusalsFor()} — the one
 * authority on `compliance_suppressions`, called rather than re-implemented,
 * including its purpose filter and its reassignment-date rule.
 *
 * ## ⚠️ WHAT IT DELIBERATELY WILL NOT SAY
 *
 * ⛔ **NOT ONE TENANT-SCOPED ROW.** `opt_outs` admits `OptOutScope::Tenant`
 * rows carrying a `business_id`, and `suppression_list` is a whole table of
 * them. Rendering either would turn a platform screen into a cross-tenant
 * customer-graph query keyed on a phone number: *"these four businesses have a
 * relationship with this person."* That is a fact about four tenants that a
 * carrier complaint does not need and this screen has no business assembling —
 * `CLAUDE.md` §Operating instructions, tiebreaker (2). **The absence is stated
 * on the page rather than left to be discovered**, and whether staff should be
 * able to ask it at all is decision 10884's open question.
 *
 * ⛔ **AND `suppression_list` COULD NOT BE READ HERE EVEN IF IT SHOULD BE.** It
 * is `ENABLE`+`FORCE` row-level security on `business_id = current_setting
 * ('app.business_id')`, so a tenant-less query matches zero rows **and reports
 * success** — the same shape `CLAUDE.md` warns about for a migration backfill.
 * A future lane that wants this must enumerate businesses, which `businesses`'
 * own two policies forbid.
 *
 * ## ⚠️ IT REFUSES TO ANSWER AT ALL WHILE THE HASH EPOCH IS UNREADABLE
 *
 * ⛔ **THIS IS THE ARM THAT MATTERS AND IT IS EASY TO MISS.** Every row here is
 * matched on `Identifier::hash()`, which is HMAC'd with `APP_KEY`. After a key
 * rotation every stored digest is unmatchable at once, and an unguarded query
 * would return **nothing** — rendering as *"we have no record of this number"*,
 * which is the worst available answer to a carrier and is indistinguishable
 * from the true one. {@see SuppressionRegistry::refusalsFor()} fails closed the
 * same way for the same reason (8080); this reports `readable: false` and the
 * screen withholds every panel that depends on a digest.
 */
final readonly class NumberDossier
{
    /**
     * ⚠️ **`Voice`, NOT `Sms`, AND IT IS THE READ THAT WIDENS.**
     * {@see OutreachChannel::suppressionSharedWith()} answers
     * `[Voice, Sms, Whatsapp]` for `Voice` and `[Sms]` for `Sms` — so asking as
     * `Sms` would hide a WhatsApp-typed row filed against the same number. A
     * page whose subject is *the number* wants every phone-shaped row there is,
     * and the digest is identical across all three because
     * {@see Identifier::hash()} hashes the normalised value and nothing else.
     */
    private const OutreachChannel WIDEST_PHONE_READ = OutreachChannel::Voice;

    public function __construct(
        private SuppressionRegistry $registers,
        private IdentifierHashEpochs $epochs,
    ) {}

    /**
     * The E.164 form of whatever an operator typed, or null when it is not a
     * phone number this application can read.
     *
     * ⚠️ **THE SAME NORMALISER THE SEND PATH USES**, for `numbers:history`'s
     * stated reason: an operator at 2am types `512-555-9999` off a carrier
     * email, and an exact match against a stored value would report "nothing
     * on record" for a number that is on record.
     */
    public function normalise(string $typed): ?string
    {
        return Identifier::normalise(trim($typed), self::WIDEST_PHONE_READ);
    }

    /**
     * Whether a stored digest can still be compared against a fresh one.
     *
     * ⚠️ Delegated one hop, never re-derived — {@see SuppressionReadability}'s
     * own docblock records a mutation in which three view variables carrying
     * one fact disagreed.
     */
    public function readable(): bool
    {
        return $this->epochs->readability()->isReadable();
    }

    /**
     * Every platform-scoped refusal standing in `opt_outs` against this
     * number, newest first.
     *
     * ⚠️ **A REFUSAL AND ITS REVERSAL ARE TWO ROWS, SO BOTH ARE RETURNED** —
     * see {@see self::lifts()}. This method does not filter out a lifted
     * refusal, because *"they said STOP in March and we were told to start
     * again in June"* is the answer to a carrier's question and *"nothing on
     * record"* is not.
     *
     * @return Collection<int, OptOut>
     */
    public function stops(string $e164): Collection
    {
        $hash = Identifier::hash($e164, self::WIDEST_PHONE_READ);

        if ($hash === null || ! $this->readable()) {
            return collect();
        }

        return OptOut::query()
            ->whereIn('identifier_type', self::WIDEST_PHONE_READ->suppressionSharedWith())
            ->where('value_hash', $hash)
            // Platform scope only — see this class's docblock. A tenant-scoped
            // row names a business, and naming one here is the cross-tenant
            // read this screen refuses to be.
            ->where('scope', OptOutScope::Platform)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Every platform-scoped lift recorded against this number, newest first.
     *
     * ⚠️ **WITHOUT THESE THE STOPS ARE MISLEADING.** `opt_outs` is append-only
     * and a release is a `suppression_lifts` row matched on `lift_generation`,
     * so a page showing only refusals would report somebody as permanently
     * blocked who has been reachable again since June.
     *
     * @return Collection<int, SuppressionLift>
     */
    public function lifts(string $e164): Collection
    {
        $hash = Identifier::hash($e164, self::WIDEST_PHONE_READ);

        if ($hash === null || ! $this->readable()) {
            return collect();
        }

        return SuppressionLift::query()
            ->whereIn('identifier_type', self::WIDEST_PHONE_READ->suppressionSharedWith())
            ->where('value_hash', $hash)
            ->where('scope', OptOutScope::Platform)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Which compliance registers refuse this number, for each purpose.
     *
     * ⚠️ **BOTH PURPOSES, BECAUSE THEY GIVE DIFFERENT ANSWERS AND THE
     * DIFFERENCE IS THE POINT.** `ComplianceList::appliesTo()` blocks a federal
     * DNC listing on marketing only; a litigator listing blocks both. An
     * operator answering *"why did you text me"* needs to know which kind of
     * message the register would have stopped.
     *
     * ⚠️ **NO CONSENT DATE IS PASSED**, so every reassignment row applies. That
     * is `refusalsFor()`'s own safe reading: this screen has no consent record
     * to protect, and a page is not a send.
     *
     * @return array{marketing: list<ComplianceList>, transactional: list<ComplianceList>}
     */
    public function registerRefusals(string $e164): array
    {
        return [
            'marketing' => $this->registers->refusalsFor(
                $e164,
                self::WIDEST_PHONE_READ,
                OutreachPurpose::Marketing,
            ),
            'transactional' => $this->registers->refusalsFor(
                $e164,
                self::WIDEST_PHONE_READ,
                OutreachPurpose::Transactional,
            ),
        ];
    }

    /**
     * Which registers a marketing send depends on have never been imported.
     *
     * ⛔ **THIS IS WHAT STOPS "NOTHING ON RECORD" BEING A LIE.** The shipped
     * state of this platform is *no register loaded* — the federal Do Not Call
     * subscription, the Reassigned Numbers Database and the litigator lists are
     * all paid products and none is in this repository. A clean
     * `registerRefusals()` against an empty register means **nobody has ever
     * scrubbed this number**, which is a completely different sentence from
     * *"we checked and it is not listed"*, and 1600 records this exact
     * distinction being got wrong inside the send gate itself.
     *
     * @return list<ComplianceList>
     */
    public function registersNeverLoaded(): array
    {
        return $this->registers->missingForMarketing(self::WIDEST_PHONE_READ);
    }
}
