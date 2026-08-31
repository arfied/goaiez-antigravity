<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\BrandRegistrationStatus;
use App\Exceptions\BrandRegistrationRefused;
use App\Models\BrandRegistration;
use App\Services\AuditService;
use App\Services\Campaigns\BroadcastPreconditions;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * The one place a tenant's own 10DLC filing is recorded, approved or refused —
 * decision 3310's first precondition.
 *
 * ## Why this exists at all, and why it is not a column on `businesses`
 *
 * ⛔ **NOTHING IN `app/` MODELLED A TENANT'S OWN BRAND BEFORE THIS.** `26`
 * specifies `brand_registrations` and `brand_registration_events` and neither was
 * ever built; every mention of 10DLC in this codebase is about the **platform's**
 * filing, which is one brand shared by every tenant (2101). 3310 makes the
 * tenant's own filing a precondition of a feature, so the fact has to be
 * *recorded* somewhere a send-time guard can ask — and `CLAUDE.md`'s first
 * recurring failure shape is what decides the rest: a boolean on `businesses`
 * would have had no writer, and *"an isolation test passes perfectly against a
 * table nothing writes"*.
 *
 * ⚠️ **SO THE WRITER SHIPS IN THE SAME SLICE AS THE READER.**
 * `sms:brand-registration` is the Ops command that calls every method here, on
 * `RegisterSendingNumber`'s precedent — there is no tenant-facing screen for a
 * number either, and every toggle is a future support ticket.
 *
 * ⛔ **AND THAT PARAGRAPH READ AS THOUGH NOTHING TENANT-FACING WOULD EVER READ
 * THIS SERVICE, WHICH STOPPED BEING TRUE ON 2026-08-18 (5329, 5421).** Every
 * *writer* is still Ops-only and that half is unchanged — there is no
 * tenant-facing way to file, approve or refuse anything, and there must not be.
 * What changed is that `App\Livewire\Account\Texting` now *reads*
 * through {@see self::mostRecent()}, because a tenant who is told they cannot run a
 * campaign and never told how long the wait is or where they are in it is
 * 5329's finding. **A status surface is not a toggle**: it has no action, writes
 * nothing, and `CLAUDE.md`'s standing rule is about controls rather than about
 * facts a tenant is waiting on.
 *
 * ## What "approved" is allowed to mean
 *
 * ⛔ **BRAND *AND* CAMPAIGN, NEVER ONE OF THEM.** A carrier-approved brand with
 * no approved campaign routes nothing, so {@see self::approve()} requires both
 * provider references and the CHECK requires them again (216's two layers). The
 * reader is {@see BroadcastPreconditions}, on the hot path, per recipient.
 *
 * ⚠️ **THIS SERVICE NEVER CONSULTS OR TOUCHES A NUMBER.** The brand and the
 * number are two independent facts and 3310 names them separately; folding them
 * together here would make one guard's failure hide the other's, which is 398's
 * shape. {@see TenantNumbers::adoptOwnNumber()} is the other half and refuses on
 * its own evidence.
 */
final class BrandRegistrations
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Record that this tenant has filed on their own account.
     *
     * ⚠️ **A SUBMISSION IS NOT AN APPROVAL AND THE STATUS SAYS SO.** Roughly one
     * to two weeks pass between the two for the platform's own filing (1562) and
     * there is no reason a tenant's is faster, so the gap is the ordinary state
     * of this row rather than a transient one.
     *
     * @throws BrandRegistrationRefused when this tenant already has a filing
     *                                  that has not been refused
     */
    public function submit(string $provider, string $actor): BrandRegistration
    {
        Tenancy::idOrFail();

        $live = $this->live();

        if ($live !== null) {
            // ⚠️ **REFUSED RATHER THAN RETURNED, UNLIKE EVERY OTHER IDEMPOTENT
            // OPS COMMAND HERE.** `addToPool()` and `RegisterSendingNumber` are
            // re-run as deploy steps and hand back what is already there; a
            // second filing is not a re-run, it is money spent at the vendor and
            // a second brand the carriers will see. The partial unique index
            // refuses it too — this is what turns SQLSTATE 23505 into a sentence.
            throw BrandRegistrationRefused::because(
                "This business already has a {$live->status->value} 10DLC filing. A second live "
                .'filing would make "is this tenant approved" a question with two answers, and the '
                .'guard would read whichever sorted first. Refuse the existing one before re-filing.'
            );
        }

        $registration = new BrandRegistration;
        $registration->fill([
            'provider' => $provider,
            'submitted_at' => now(),
            'submitted_by' => $actor,
        ]);

        // Assigned rather than filled: the status is what a send-time guard
        // reads, so it is this service's to set and never a caller's array's.
        $registration->status = BrandRegistrationStatus::Submitted;
        $registration->save();

        $this->audit->record('brand_registration.submitted', $actor, $registration, [
            'provider' => $provider,
        ]);

        return $registration;
    }

    /**
     * The carriers approved it. This is what unlocks broadcasting.
     *
     * ⛔ **SENSITIVE, AND THE AUDIT ENTRY IS NOT DECORATION.** This one write is
     * what lets a tenant send marketing text to a list the platform did not
     * capture consent for, on a brand whose complaint rate is theirs. *"Who said
     * the carriers had approved this, and when"* is the question asked after a
     * complaint, and `audit_log` is the only append-only place that answers it.
     *
     * @throws BrandRegistrationRefused when either provider reference is missing
     *                                  or the filing is not this tenant's
     */
    public function approve(
        BrandRegistration $registration,
        string $providerBrandId,
        string $providerCampaignId,
        string $actor,
    ): BrandRegistration {
        $this->assertBelongsToTenant($registration);

        if ($registration->status === BrandRegistrationStatus::Rejected) {
            throw BrandRegistrationRefused::because(
                'A refused filing cannot be approved. Re-filing is a new submission with its own '
                .'history — the row that says why the carriers refused is the one somebody will '
                .'be asked about.'
            );
        }

        $brandId = trim($providerBrandId);
        $campaignId = trim($providerCampaignId);

        if ($brandId === '' || $campaignId === '') {
            // ⛔ **BOTH, OR NEITHER.** An approved brand with no approved
            // campaign routes nothing, so an approval missing either reference
            // is a green light for a route that does not exist. The CHECK says
            // the same; this is what names the rule instead of quoting the
            // constraint.
            throw BrandRegistrationRefused::because(
                'An approval needs the brand reference and the campaign reference. A brand the '
                .'carriers approved with no approved campaign cannot carry a single message, and '
                .'a broadcast guard reading this row would wave it through.'
            );
        }

        return DB::transaction(function () use ($registration, $brandId, $campaignId, $actor): BrandRegistration {
            $registration->status = BrandRegistrationStatus::Approved;
            $registration->provider_brand_id = $brandId;
            $registration->provider_campaign_id = $campaignId;
            $registration->approved_at = now();
            $registration->save();

            $this->audit->record('brand_registration.approved', $actor, $registration, [
                'provider' => $registration->provider,
            ]);

            return $registration;
        });
    }

    /**
     * The carriers refused it.
     *
     * ⚠️ **THE ROW STAYS AND LEAVES THE UNIQUE INDEX**, which is what lets a
     * re-file happen without editing away the reason the first one failed —
     * `SendingPause`'s retention argument, one table over.
     *
     * @throws BrandRegistrationRefused when no reason is given, or the filing is
     *                                  not this tenant's
     */
    public function reject(BrandRegistration $registration, string $reason, string $actor): BrandRegistration
    {
        $this->assertBelongsToTenant($registration);

        $reason = trim($reason);

        if ($reason === '') {
            throw BrandRegistrationRefused::because(
                'A refusal needs a reason. The tenant will ask what to fix, and a status with no '
                .'sentence beside it is a support ticket with no answer.'
            );
        }

        return DB::transaction(function () use ($registration, $reason, $actor): BrandRegistration {
            $registration->status = BrandRegistrationStatus::Rejected;
            $registration->rejection_reason = $reason;
            $registration->rejected_at = now();
            // The approval is withdrawn with the status, so the row cannot read
            // as "approved once" to anybody scanning the columns rather than the
            // status. The CHECK permits a rejected row to carry them; leaving
            // them would be a fact about a route that no longer exists.
            $registration->provider_brand_id = null;
            $registration->provider_campaign_id = null;
            $registration->approved_at = null;
            $registration->save();

            $this->audit->record('brand_registration.rejected', $actor, $registration, [
                'provider' => $registration->provider,
            ]);

            return $registration;
        });
    }

    /**
     * May this tenant send on their own 10DLC brand right now?
     *
     * ⚠️ **ASKED OF THE DATABASE, NOT OF A ROW SOMEBODY IS HOLDING.** This runs
     * per recipient inside a campaign pass, so the answer has to be able to
     * change while a run is in flight — a filing refused at 3am must stop the
     * campaign that started at midnight. That is 2113's whole argument about the
     * containment being consulted inside the loop rather than before it.
     */
    public function isApproved(): bool
    {
        Tenancy::idOrFail();

        return BrandRegistration::query()
            ->where('status', BrandRegistrationStatus::Approved->value)
            ->exists();
    }

    /**
     * This tenant's live filing, approved or still waiting.
     *
     * ⚠️ Ordered by `id`, never `created_at`: that column is nullable here as it
     * is on forty others, and Postgres sorts NULL first on a descending order —
     * decision 289, which is an `ArchitectureTest` lint.
     */
    public function live(): ?BrandRegistration
    {
        Tenancy::idOrFail();

        return BrandRegistration::query()
            ->where('status', '!=', BrandRegistrationStatus::Rejected->value)
            ->orderBy('id')
            ->first();
    }

    /**
     * The filing this tenant is looking at — **refused ones included**.
     *
     * ⛔ **THE DIFFERENCE FROM {@see self::live()} IS THE WHOLE REASON THIS
     * EXISTS, AND GETTING IT WRONG IS SILENT.** `live()` excludes `rejected` on
     * purpose: its readers ask *"is there a filing in play"*, and a refused row
     * is not one. A **screen** asking the same question would show a tenant
     * whose filing the carriers turned down exactly what a tenant who has never
     * filed sees — *"nothing has been filed for you yet"* — so the one state
     * with a reason attached, and the only one where {@see self::reject()}'s own
     * argument (*"the tenant will ask what to fix"*) applies, would be the one
     * state the tenant could never see. The row is right, the scope is right,
     * and the page is a lie.
     *
     * ⚠️ **ORDERED BY `id` DESCENDING, NEVER `created_at`** — decision 289, an
     * `ArchitectureTest` lint: that column is nullable here as it is on forty
     * others and Postgres sorts NULL first on a descending order. Descending
     * rather than `live()`'s ascending because a re-file after a refusal is a
     * *newer* row (the refused one stays, by design), and the newest is the one
     * the tenant is waiting on.
     *
     * ⚠️ **NAMED `mostRecent()` AND NOT `latest()`, WHICH IS A LINT AND NOT A
     * PREFERENCE.** `ConventionsTest`'s *"no descending order relies on Postgres
     * putting NULLs first"* refuses `->latest(` anywhere in `app/` unless it is
     * keyed on `id`, because Eloquent's `latest()` defaults to `created_at` —
     * nullable on forty tables here — and Postgres sorts NULL first descending
     * (decision 289). A service method wearing that name puts the ambiguity back
     * at every call site, where the lint can no longer tell the two apart. It
     * caught this method's first spelling on the whole-suite run.
     *
     * ⚠️ **NOT A SENDING-PATH ANSWER AND NOTHING MAY USE IT AS ONE.** The send
     * path asks {@see self::isApproved()}, which asks the database for an
     * approved row and nothing else; this hands back whatever the last filing
     * was, refusal included.
     */
    public function mostRecent(): ?BrandRegistration
    {
        Tenancy::idOrFail();

        return BrandRegistration::query()
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Refuse to act on another tenant's filing.
     *
     * The read path is already scoped, so a stray row returns nothing. The write
     * path is not: the model comes from a caller while the tenant comes from the
     * ambient context, so tenant A could approve tenant B's filing and the
     * approval would be filed under a business that never made it. This is the
     * *wrong tenant* case RLS cannot catch — `Campaigns::assertBelongsToTenant()`
     * and `ConsentService`'s reasoning, one table over.
     *
     * @throws BrandRegistrationRefused
     */
    private function assertBelongsToTenant(BrandRegistration $registration): void
    {
        if ($registration->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw BrandRegistrationRefused::because(
            'That 10DLC filing belongs to another tenant. An approval written from here would '
            .'unlock broadcasting for a business whose carriers never approved anything.'
        );
    }
}
