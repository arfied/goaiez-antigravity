<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\CaptureSurface;
use App\Enums\ImpersonationCapability;
use App\Enums\OutreachChannel;
use App\Enums\ProofHashDomain;
use App\Models\Business;
use App\Models\OwnerNotificationConsent;
use App\Models\OwnerNotifyNumber;
use App\Services\AuditService;
use App\Services\Impersonation\Impersonation;
use App\Support\Identifier;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Whether — and where — this business's own account holder may be texted
 * (10540, the owner ruling of 2026-08-27).
 *
 * ⛔ **THIS IS NOT `ConsentService`, AND IT DELIBERATELY DOES NOT CALL IT.**
 * That class decides whether a **tenant's customer** may be messaged: it walks
 * `consent_records`, the platform-scoped `opt_outs` register, the DNC and
 * litigator lists, reassignment and the state mini-TCPA rules, because Lane A
 * and Lane B are both "the platform messaging somebody the tenant does
 * business with". None of that machinery is the right shape for "the
 * platform messaging its own customer about their own account" — the account
 * holder is not on a Do Not Call registry check for OUR product, is not
 * subject to a state's marketing-hours rule for a transactional account
 * notice, and above all is **not a `Customer` row**, which every one of those
 * checks is keyed on. Reusing `ConsentService::decide()` here would either
 * silently apply rules written for a different population or need its own new
 * branch through the middle of a 1,800-line class that this file's own brief
 * says has "different questions" for a different person.
 *
 * ## The split: evidence, and the current answer
 *
 * `owner_notification_consents` (`{@see OwnerNotificationConsent}`) is
 * append-only evidence — what the owner was shown, and when, and — since
 * wave 39 lane A (10660) — which number it was shown to. It is written once
 * per disclosure and never edited. `owner_notify_numbers`
 * (`{@see OwnerNotifyNumber}`) is the mutable, current answer — one row per
 * business, the number to send to and whether they have said STOP — the same
 * split `consent_records` / `customers.sms_consent` already draws for the
 * customer channel.
 *
 * ## Why `ImpersonationCapability::RecordConsent` is reused rather than a new
 * case added
 *
 * That capability's docblock already states the general rule this needs: *"a
 * consent record… proof that support can author is not proof"*. Adding a
 * second, narrower case for this one extra table would duplicate an argument
 * that does not change shape for a second kind of consent — the harm is
 * identical (a support agent manufacturing the one record that proves somebody
 * agreed to be contacted), so the existing gate is asked instead of a new one
 * being declared beside it.
 */
final class OwnerConsentService
{
    /**
     * The actor recorded against a STOP/START this class learns about from a
     * carrier reply rather than from a person using this application's own
     * screens — `InboundMessages::ACTOR`'s convention, kept independent of
     * it: that class is lane C's, and a shared constant would couple two
     * files across a lane boundary for a string neither can be relied on to
     * keep in sync with the other's rename.
     */
    private const string CARRIER_ACTOR = 'carrier:sms';

    public function __construct(
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
    ) {}

    /**
     * Record the account holder's consent to be texted, at one mobile number,
     * for the business currently in context.
     *
     * ⚠️ **THE PROOF IS VALIDATED BEFORE ANY ROW EXISTS**, `TermsAcceptances`'
     * and `AutoRenewalAcknowledgements`' property: an unproved record is
     * unrepresentable rather than merely unusual.
     *
     * ⚠️ **A FRESH CAPTURE ALWAYS CLEARS A PRIOR STOP.** This is a judgement
     * call, recorded rather than made quietly (decision 10540's block): the
     * owner is shown the full disclosure again, on a page this application
     * renders, and ticks an unchecked box again — that is a distinct,
     * provable act of consent, not a repeat of the one a STOP withdrew. The
     * alternative — requiring a carrier START before a web re-consent takes
     * effect — was considered and rejected as the more confusing of the two
     * for a wizard or an account-settings screen, where the person doing the
     * re-consenting is looking at a form, not a text thread.
     *
     * @param  array<string, mixed>  $proof  url, ip_hash, user_agent.
     *
     * ⚠️ **`$consentChecked` IS REQUIRED AND CARRIES NO DEFAULT — 10660's own
     * finding, "the guard that cannot fail".** Until this parameter existed,
     * `checkbox_state` was hardcoded to `'checked_by_user'` inside this
     * method regardless of what actually happened on screen, so
     * `ConsentProof::rejectPreCheckedBox()` — the guard `29` §12.1 lists as
     * build-failing — could never fire for this table no matter what a
     * future caller passed: the value it checked was never the caller's to
     * vary. `Settings::saveOwnerNotify()` and
     * `App\Livewire\Setup\Done::saveOwnerNotify()` both already validate
     * `['ownerConsent' => ['accepted']]` before calling this method, so this
     * parameter costs those two callers nothing; what it buys is that a
     * THIRD caller — a support tool, a data migration, an import routine —
     * has to say explicitly that a box was ticked rather than getting the
     * claim for free from this method's own internals.
     * ⚠️ **`AutoRenewalAcknowledgements`, `FeedbackSubmission` and
     * `PhiAnalysisConsent` carry the identical hardcoded pattern and are
     * deliberately NOT touched here** — see decision 10660's block for why
     * the owner channel is the one of the four where a false record becomes
     * a carrier complaint rather than a billing or feedback dispute, and why
     * that difference is the argument for fixing one file and not four.
     *
     * @throws InvalidArgumentException when the mobile number will not
     *                                  normalise as a US phone number.
     */
    public function capture(string $mobile, array $proof, string $actor, bool $consentChecked): OwnerNotificationConsent
    {
        $normalised = Identifier::normalise($mobile, OutreachChannel::Sms);

        if ($normalised === null) {
            throw new InvalidArgumentException(
                'That does not look like a US mobile number. Check the digits and try again.',
            );
        }

        // ⚠️ SUPPORT CANNOT CONSENT ON THE OWNER'S BEHALF — see this class's
        // own docblock for why `RecordConsent` is reused rather than a new
        // capability declared.
        $this->impersonation->refuse(ImpersonationCapability::RecordConsent);

        $businessId = Tenancy::idOrFail();

        $blob = $proof + [
            // ⚠️ DERIVED FROM THE CALLER'S OWN CLAIM, NEVER A LITERAL — see
            // this method's own docblock. Any value other than
            // 'checked_by_user' fails ConsentProof::rejectPreCheckedBox()
            // below, exactly as it should for a caller passing false.
            'checkbox_state' => $consentChecked ? 'checked_by_user' : 'not_checked',
            'disclosure_text' => OwnerNotifyDisclosure::TEXT,
        ];

        // Constructed for its guards AND for what it returns — the scoped
        // array is what the column stores, with `ip_hash` re-hashed under
        // this table's own domain so it cannot be joined against anybody
        // else's copy of the same address (7888).
        $blob = (new ConsentProof(
            $blob,
            CaptureSurface::OwnerNotify,
            'checkbox',
            ProofHashDomain::OwnerNotificationConsents,
        ))->proof;

        return DB::transaction(function () use ($businessId, $normalised, $blob, $actor): OwnerNotificationConsent {
            // ⚠️ **EVIDENCE FIRST, OPERATIONAL SECOND — DELIBERATE ORDERING,
            // NOT ATOMICITY (BOTH ARE ALREADY IN ONE TRANSACTION).** This row
            // is what makes an owner correcting a mistyped digit two
            // genuinely distinguishable, permanent facts rather than one
            // overwritten current-state row and an evidence row indistinct
            // from its predecessor (10660's defect). `e164` names the exact
            // number THIS disclosure was shown and agreed to; it never
            // changes after the row is written, unlike the row below.
            $consent = OwnerNotificationConsent::create([
                'channel' => OutreachChannel::Sms,
                'method' => 'checkbox',
                'e164' => $normalised,
                'accepted_by' => $actor,
                'disclosure_version' => OwnerNotifyDisclosure::VERSION,
                'proof' => $blob,
                'created_at' => now(),
            ]);

            // ⚠️ **`updateOrCreate` OVERWRITES THE PRIOR NUMBER HERE, AND
            // THAT IS ARGUED RATHER THAN AN OVERSIGHT — 10660's phase 2
            // question, answered.** This row is documented, above and on its
            // own creating migration, as the CURRENT-STATE answer to "does
            // this business have a notify number, and is it stopped" — it
            // has never claimed to be history. Every number this business
            // has ever consented to be texted at is preserved permanently,
            // in order, on `owner_notification_consents` above (now that
            // every row names its own `e164`), so overwriting this row loses
            // nothing the evidence table does not already keep — and
            // `permit()` below now cross-checks against that table directly,
            // rather than trusting this row alone.
            OwnerNotifyNumber::query()->updateOrCreate(
                ['business_id' => $businessId],
                ['e164' => $normalised, 'stopped_at' => null],
            );

            $this->audit->record('owner_channel.consent_captured', $actor, $consent, [
                'disclosure_version' => OwnerNotifyDisclosure::VERSION,
            ]);

            return $consent;
        });
    }

    /**
     * Authorisation to text this business's own account holder, or null when
     * there is none.
     *
     * ⚠️ **NULL IS THE SAFE DEFAULT, `SendPermit`'s AND `ConsentService`'s OWN
     * SHAPE.** No row, or a row whose `stopped_at` is set: both refuse the
     * same way, silently, because refusing to send is safe and routine and a
     * caller that cannot tell "never consented" from "asked us to stop" apart
     * cannot accidentally treat one as the other either.
     *
     * ⚠️ **AND, SINCE WAVE 39 LANE A (10660), A THIRD CHECK THAT MAKES
     * {@see PlatformTexter}'s DOCBLOCK TRUE RATHER THAN ASPIRATIONAL.** That
     * class claims a permit is "minted only … from a recorded consent
     * event" — and until this check existed, that was true only because
     * `OwnerConsentService` (this class) is the sole writer of
     * `owner_notify_numbers`, held there by an `ArchitectureTest` lint in `OwnerChannelTest.php` that
     * scans Eloquent call sites in `app/` and cannot see a raw
     * `DB::table('owner_notify_numbers')` insert, a factory misused outside
     * a test, or a future method added to this very class that sets `e164`
     * without calling {@see self::capture()}. This is the belt beside that
     * lint's braces: a business with an operational row but NO
     * `owner_notification_consents` row at all has no consent event on file
     * by definition, whatever the operational row says, and the permit
     * refuses exactly as it does for "never consented" above. ⚠️ **Not an
     * exact-number match** — a row from before the `e164` column existed on
     * the evidence table still counts as evidence, because it still proves a
     * disclosure happened; requiring the two `e164` values to agree would
     * silently revoke every permit granted before 10660 shipped, which is a
     * regression this fix must not cause on its way to closing a gap.
     */
    public function permit(Business $business): ?OwnerSendPermit
    {
        $number = $this->currentNumberFor($business);

        if ($number === null || $number->stopped_at !== null) {
            return null;
        }

        $hasConsentEvidence = OwnerNotificationConsent::query()
            ->where('business_id', $business->getKey())
            ->exists();

        if (! $hasConsentEvidence) {
            return null;
        }

        return OwnerSendPermit::grant($number);
    }

    /**
     * The current operational row for a business, however it now stands —
     * live, stopped, or absent.
     *
     * ⛔ **"`App\Livewire\Admin\OwnerNotifyConsents` IS THE ONE OTHER CALLER"
     * WAS TRUE AT 10660 AND STOPPED BEING TRUE IN WAVE 40 — CORRECTED HERE
     * (10838).** Lane C's `App\Livewire\Admin\NumberLookup` is a second, and
     * the count is deliberately not restated: **a number in this sentence is
     * what went stale**, and the property below is what the paragraph is
     * actually for. ⚠️ **Do not re-add one** — the readers are enumerable by
     * grep and by `php artisan ops:method-callers`, and a copy of their length
     * in prose is 8460's shape. The "one writer" this class's own model docblock and the
     * `ArchitectureTest` lint in `OwnerChannelTest.php` describe is really "the one file that touches
     * this table at all" — the table carries no RLS predicate to fall back
     * on (`USING (true)`, see its own creating migration), so a second
     * reader is a method here rather than a second entry on that lint's
     * allowlist. The admin screen shows a STOPPED row too, which
     * {@see self::permit()} above deliberately does not distinguish from
     * "never consented" — this method is the one place both questions can be
     * answered from the same row.
     */
    public function currentNumberFor(Business $business): ?OwnerNotifyNumber
    {
        return OwnerNotifyNumber::query()
            ->where('business_id', $business->getKey())
            ->first();
    }

    /**
     * Every business whose registered owner-notify number is this sender —
     * the reverse lookup `App\Services\Sms\InboundMessages` needs to identify
     * an inbound owner reply before it is filed as a customer's, **and, since
     * wave 40, the one a staff screen needs to answer a carrier complaint about
     * a number** (lane C's `App\Livewire\Admin\NumberLookup`, 10891). The
     * framing above named one caller and the question is now asked by two.
     *
     * ⛔ **IT DELIBERATELY DOES NOT FILTER `stopped_at`, AND TWO INDEPENDENT
     * ARGUMENTS SAY SO — 10830 AND 10891.** The brief that found the
     * stopped-owner defect prescribed narrowing this method, and narrowing it
     * is wrong twice over. **First**, `InboundMessages::handle()` runs this
     * lookup **once** and hands it to the STOP, START **and** ordinary-text
     * arms, so a stopped row filtered out here leaves an owner who said STOP
     * unable ever to say START — driven red against the pre-existing test that
     * proves it, not reasoned about. **Second**, a carrier complaint reaching
     * the staff screen is *most likely* about a number that has since opted
     * out, so the narrowing would break that screen on exactly the case it
     * exists for. ✅ **A narrower question wants a SECOND method**, which is
     * {@see self::hasSaidStop()}, asked by the one arm that needs it.
     *
     * ⚠️ **A LIST, NEVER A SINGLE ANSWER, BECAUSE THE SAME PHONE CAN GENUINELY
     * REGISTER MORE THAN ONE BUSINESS** — one person owning two businesses and
     * reusing one mobile for both, which `businesses.owner_user_id` carrying
     * no uniqueness constraint makes a real shape rather than a hypothetical
     * one. Collapsing that to "the" business would guess, and a guess here
     * means attributing one business's reply to another's thread. The caller
     * decides what an ambiguous match means; this method only reports it.
     *
     * @return list<int>
     */
    public function businessesFor(string $from): array
    {
        $normalised = Identifier::normalise($from, OutreachChannel::Sms);

        if ($normalised === null) {
            return [];
        }

        return array_values(OwnerNotifyNumber::query()
            ->where('e164', $normalised)
            ->pluck('business_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all());
    }

    /**
     * Has this business's account holder withdrawn owner-channel consent?
     *
     * ⛔ **THIS EXISTS BECAUSE {@see self::businessesFor()} MUST NOT ANSWER IT,
     * AND THE BRIEF THAT FOUND THE GAP PRESCRIBED THE ONE FIX THAT BREAKS
     * START** (10830). That method is the reverse lookup
     * `App\Services\Sms\InboundMessages::handle()` runs **once** and hands to
     * three arms — STOP, START and ordinary text — so filtering `stopped_at`
     * inside it would make {@see self::start()} unable to find the very row it
     * exists to un-stop: an owner who texted STOP could never text START again.
     * **The question belongs on the arm that needs it**, which is the ordinary-
     * text one, and this is that question asked by id rather than by number.
     *
     * ⚠️ **`true` FOR A BUSINESS WITH NO ROW AT ALL IS UNREACHABLE FROM THAT
     * CALLER AND IS STILL THE SAFE ANSWER.** `businessesFor()` only returns a
     * business that HAS a row, so the no-row case cannot arise there; a future
     * caller holding an arbitrary id gets *"treat them as stopped"*, which is
     * the refusing direction.
     *
     * ⚠️ **NOT {@see self::permit()}.** That method also demands a
     * `owner_notification_consents` evidence row, because it authorises a
     * **send**. This authorises nothing — it decides whether words somebody
     * typed are kept — and consent evidence is not the question a withdrawal
     * asks.
     */
    public function hasSaidStop(int $businessId): bool
    {
        $number = OwnerNotifyNumber::query()
            ->where('business_id', $businessId)
            ->first();

        return $number === null || $number->stopped_at !== null;
    }

    /**
     * An owner said STOP. Idempotent, and a no-op for a business with no
     * registered, live number — there is nothing to stop.
     *
     * ⚠️ **`$actor` IS OPTIONAL, DEFAULTING TO A CARRIER LABEL, RATHER THAN
     * REQUIRED — AND THAT IS ABOUT WHO ELSE CALLS THIS, NOT ABOUT THIS
     * BEING OPTIONAL WORK (10660).** `App\Services\Sms\InboundMessages`
     * (lane C's file) calls this with no actor argument, from inside a
     * carrier webhook with no tenant established, for every business a STOP
     * matches — adding a required parameter here would be a breaking
     * signature change to a file this lane does not own. `Settings::
     * stopOwnerNotify()` passes the real actor explicitly.
     */
    public function stop(int $businessId, string $actor = self::CARRIER_ACTOR): void
    {
        $number = OwnerNotifyNumber::query()
            ->where('business_id', $businessId)
            ->whereNull('stopped_at')
            ->first();

        if ($number === null) {
            return;
        }

        $number->update(['stopped_at' => now()]);

        $this->recordConsentChangeAudit($businessId, 'owner_channel.consent_withdrawn', $actor, $number);
    }

    /**
     * An owner said START. The reversal half of {@see self::stop()}, on the
     * same terms `App\Services\Sms\InboundMessages::start()` already applies
     * to a customer's own opt-back-in.
     *
     * ⚠️ **`$actor` IS OPTIONAL FOR {@see self::stop()}'s EXACT REASON.**
     */
    public function start(int $businessId, string $actor = self::CARRIER_ACTOR): void
    {
        $number = OwnerNotifyNumber::query()
            ->where('business_id', $businessId)
            ->whereNotNull('stopped_at')
            ->first();

        if ($number === null) {
            return;
        }

        $number->update(['stopped_at' => null]);

        $this->recordConsentChangeAudit($businessId, 'owner_channel.consent_resumed', $actor, $number);
    }

    /**
     * `29` §2: every sensitive action reaches an append-only audit log — and
     * withdrawing consent is at least as sensitive as granting it, which
     * {@see self::capture()} has always audited. Added 10660: `stop()` and
     * `start()` wrote `stopped_at` with no audit row at all until this
     * existed.
     *
     * ⚠️ **BEST-EFFORT, `InboundMessages::countAgainstSender()`'s OWN
     * REASONING, FOR THE SAME REASON.** The STOP or START this records has
     * already taken effect by the time this runs; a failure writing the
     * audit entry must not undo it, and — because {@see self::stop()} and
     * {@see self::start()} are the reverse-lookup path a carrier webhook
     * calls with NO TENANT ESTABLISHED AT ALL — `AuditService::record()`'s
     * own `Tenancy::idOrFail()` would throw there without the explicit
     * `Tenancy::actingAs()` below, taking a 200 response to Infobip down
     * with it over a logging failure.
     */
    private function recordConsentChangeAudit(int $businessId, string $action, string $actor, OwnerNotifyNumber $number): void
    {
        try {
            Tenancy::actingAs($businessId, function () use ($action, $actor, $number): void {
                $this->audit->record($action, $actor, $number);
            });
        } catch (Throwable $e) {
            // ⚠️ THE CLASS NAME, NEVER ANYTHING ABOUT THE NUMBER OR THE
            // OWNER — `InboundMessages::countAgainstSender()`'s identical
            // rule: a failure recording an audit entry must not pay for
            // itself with the thing an audit entry exists to protect.
            Log::warning('An owner-channel consent change could not be written to the audit log.', [
                'reason' => $e::class,
                'business_id' => $businessId,
                'action' => $action,
            ]);
        }
    }
}
