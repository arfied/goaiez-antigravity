<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Console\Commands\ExecuteTenantDeletions;
use App\Enums\DataRequestKind;
use App\Enums\DataRequestStatus;
use App\Enums\ExportSource;
use App\Enums\ExportStatus;
use App\Enums\StoredObjectKind;
use App\Enums\TenantDeletionReason;
use App\Http\Controllers\Account\TenantExportDownloadController;
use App\Jobs\BuildTenantExportJob;
use App\Models\Business;
use App\Models\Customer;
use App\Models\DataRequest;
use App\Models\TenantExport;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Services\Consent\ConsentTrailEntry;
use App\Services\Export\ExportBuilder;
use App\Services\Tenant\TenantDeletion;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * `28` §9.5's data-request queue — the only reader/writer of `data_requests`.
 *
 * ## What ships
 *
 * - A dated queue for erasure, per-contact consent audit, and tenant export asks.
 * - Erasure fulfilment through {@see TenantDeletion} (two-person + cooling window).
 * - Consent audit fulfilment through {@see ConsentService::proofFor()} only —
 *   no ad-hoc SQL (§14.1 rule 6).
 * - ✅ Tenant-wide export is built, not refused (decision 1824). Filing opens a
 *   row the same way an erasure does; a second person approves it through
 *   {@see self::approveTenantExport()}, which is what dispatches
 *   {@see ExportBuilder} — reusing the `approved_by`/`approved_at` pair the
 *   `data_requests` migration already reserved for "second person for erasure
 *   confirmation / export approval". This is the approval step `28` §9.7's Fix
 *   Toolbox would have been; the Toolbox itself is not built (decision 1823).
 *
 * ## ⚠️ What does not
 *
 * Erasure is **not** crypto-shred (1380). There is no per-identity DEK. Fulfilment
 * notes say so on the row. Whoever later builds a shred owns flipping that note;
 * this class will not claim a protection it does not have.
 *
 * ## Due dates
 *
 * Statutory kinds (erasure) get {@see self::STATUTORY_DUE_DAYS} — 30 days, the
 * GDPR Art. 12(3) month, which is stricter than CCPA's 45. The schema has no
 * tenant-jurisdiction column (1594's `region_code` is messaging, not privacy),
 * so the stricter common clock is the fail-closed default rather than a guess
 * dressed as a deadline. Non-statutory kinds get the same window so nothing
 * sits undated.
 */
final class DataRequests
{
    /**
     * Days until a filed request is overdue.
     *
     * Not in the Defaults Registry: it is a statutory-adjacent safety interval
     * (1092's boundary), not a threshold an operator tunes. If support ever asks
     * a customer about it, that is the signal it has become a setting.
     */
    public const STATUTORY_DUE_DAYS = 30;

    public function __construct(
        private readonly AccountDirectory $accounts,
        private readonly TenantDeletion $deletions,
        private readonly ConsentService $consent,
        private readonly ExportBuilder $exports,
        private readonly AuditService $audit,
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    public function statutoryDueDays(): int
    {
        return $this->registry->int('support.data_requests.statutory_due_days');
    }

    /**
     * Open items, soonest due first. Closed rows are a separate history read.
     *
     * @return Collection<int, DataRequest>
     */
    public function open(): Collection
    {
        return DataRequest::query()
            ->whereIn('status', [
                DataRequestStatus::Open->value,
                DataRequestStatus::AwaitingDeletion->value,
                // ⚠️ A BUILT EXPORT IS STILL OPEN (1999). The ZIP exists and
                // nobody has collected it, which is the state an agent needs to
                // be able to see — dropping it off the queue here is what made
                // "did the tenant ever get their data" unanswerable.
                DataRequestStatus::Built->value,
            ])
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * History for one account (Account 360 compliance tab).
     *
     * @return Collection<int, DataRequest>
     */
    public function forBusiness(int $businessRef): Collection
    {
        return DataRequest::query()
            ->where('business_ref', $businessRef)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Which kind a row is, or null if it is gone — so a caller that needs to
     * route on the kind (the Ops screen's shared "second person" confirmation)
     * never has to name {@see DataRequest} itself. `TenancyTest`'s chokepoint
     * holds this table to this class and the model alone.
     */
    public function kindOf(int $id): ?DataRequestKind
    {
        return DataRequest::query()->whereKey($id)->first()?->kind;
    }

    /**
     * File a right-to-erasure ask and open the deletion request (first person).
     *
     * Does **not** start the cooling clock — that is {@see self::approveErasure()}.
     */
    public function fileErasure(int $businessId, User $requester, ?string $detail = null): DataRequest
    {
        $business = $this->requireBusiness($businessId);

        return Tenancy::actingAs((int) $business->id, function () use ($business, $requester, $detail): DataRequest {
            if ($this->openErasureFor($business) !== null) {
                throw new InvalidArgumentException(
                    'This account already has an open erasure request. Cancel it before filing another.'
                );
            }

            if ($this->deletions->pendingFor($business) !== null) {
                throw new InvalidArgumentException(
                    'This account already has an open deletion request. Cancel it before filing another.'
                );
            }

            return $this->translatingDuplicate(fn (): DataRequest => DB::transaction(function () use ($business, $requester, $detail): DataRequest {
                $deletion = $this->deletions->request(
                    $business,
                    $requester,
                    TenantDeletionReason::RightToErasure,
                    $detail,
                );

                $request = new DataRequest;
                $request->forceFill([
                    'business_id' => $business->id,
                    'business_ref' => $business->id,
                    'kind' => DataRequestKind::Erasure,
                    'status' => DataRequestStatus::Open,
                    'deletion_request_id' => (int) $deletion->getKey(),
                    'due_at' => CarbonImmutable::now()->addDays($this->statutoryDueDays()),
                    'requested_by' => $requester->id,
                    'requested_at' => now(),
                    'detail' => $this->normaliseNote($detail),
                ])->save();

                return $request;
            }));
        });
    }

    /**
     * File a hard offboard (no statutory rights claim) through the same queue.
     *
     * Uses {@see TenantDeletionReason::HardOffboard}, which is where the "no
     * statutory clock" fact is recorded and the only place it is true.
     *
     * ⚠️ **THE ROW IS STILL `kind = erasure`, AND THAT IS NOT A CONTRADICTION.**
     * It destroys the account through the same {@see TenantDeletion} and shares
     * the one-open-deletion-per-business partial index, so it has to be. This
     * docblock used to claim `DataRequestKind::isStatutory()` was false for it;
     * that method returned **true** for every erasure row, hard offboards
     * included — the exact inverse. The method is gone and
     * {@see DataRequestKind} says why. Read the deletion request's reason.
     */
    public function fileHardOffboard(int $businessId, User $requester, ?string $detail = null): DataRequest
    {
        $business = $this->requireBusiness($businessId);

        return Tenancy::actingAs((int) $business->id, function () use ($business, $requester, $detail): DataRequest {
            if ($this->openErasureFor($business) !== null || $this->deletions->pendingFor($business) !== null) {
                throw new InvalidArgumentException(
                    'This account already has an open deletion request. Cancel it before filing another.'
                );
            }

            return $this->translatingDuplicate(fn (): DataRequest => DB::transaction(function () use ($business, $requester, $detail): DataRequest {
                $deletion = $this->deletions->request(
                    $business,
                    $requester,
                    TenantDeletionReason::HardOffboard,
                    $detail,
                );

                $request = new DataRequest;
                $request->forceFill([
                    'business_id' => $business->id,
                    'business_ref' => $business->id,
                    'kind' => DataRequestKind::Erasure,
                    'status' => DataRequestStatus::Open,
                    'deletion_request_id' => (int) $deletion->getKey(),
                    'due_at' => CarbonImmutable::now()->addDays($this->statutoryDueDays()),
                    'requested_by' => $requester->id,
                    'requested_at' => now(),
                    'detail' => $this->normaliseNote($detail),
                    'outcome_note' => 'Hard offboard — no statutory rights claim. Clock is operational, not Art. 12.',
                ])->save();

                return $request;
            }));
        });
    }

    /**
     * @throws InvalidArgumentException when the id is gone
     */
    public function approveErasureById(int $id, User $approver): void
    {
        $request = DataRequest::query()->whereKey($id)->first();

        if (! $request instanceof DataRequest) {
            throw new InvalidArgumentException('That request is gone.');
        }

        $this->approveErasure($request, $approver);
    }

    /**
     * @throws InvalidArgumentException when the id is gone
     */
    public function cancelById(int $id, User $actor, ?string $reason = null): void
    {
        $request = DataRequest::query()->whereKey($id)->first();

        if (! $request instanceof DataRequest) {
            throw new InvalidArgumentException('That request is gone.');
        }

        $this->cancel($request, $actor, $reason);
    }

    /**
     * Second person confirms; starts TenantDeletion's cooling window.
     *
     * @throws InvalidArgumentException on self-approve or wrong kind/status
     */
    public function approveErasure(DataRequest $request, User $approver): void
    {
        $this->assertOpenErasure($request);

        if ((int) $request->requested_by === (int) $approver->id) {
            throw new InvalidArgumentException(
                '`28` §9.5 requires two people. The person who filed an erasure cannot approve it.'
            );
        }

        $business = $this->requireBusiness((int) $request->business_ref);

        Tenancy::actingAs((int) $business->id, function () use ($request, $approver, $business): void {
            $pending = $this->deletions->pendingFor($business);

            if ($pending === null) {
                throw new InvalidArgumentException(
                    'The linked deletion request is gone. Cancel this queue item and file again.'
                );
            }

            DB::transaction(function () use ($request, $approver, $pending): void {
                $this->deletions->confirm($pending, $approver);

                $request->forceFill([
                    'approved_by' => $approver->id,
                    'approved_at' => now(),
                    'status' => DataRequestStatus::AwaitingDeletion,
                    'outcome_note' => $this->erasureGapNote(),
                ])->save();
            });
        });
    }

    /**
     * File a per-contact consent audit and produce the trail in one step.
     *
     * No second-person approval: the path is {@see ConsentService::proofFor()},
     * which already exists and is the Lane build-failing surface. A second
     * approver on a read of stored proof adds ceremony without a second control.
     */
    public function fileAndFulfillConsentAudit(
        int $businessId,
        int $customerId,
        User $requester,
        ?string $detail = null,
    ): DataRequest {
        $business = $this->requireBusiness($businessId);

        return Tenancy::actingAs((int) $business->id, function () use ($business, $customerId, $requester, $detail): DataRequest {
            $customer = Customer::query()->whereKey($customerId)->first();

            if (! $customer instanceof Customer) {
                throw new InvalidArgumentException('No contact matches that id in this account.');
            }

            $trail = $this->consent->proofFor($customer);

            $payload = [
                'customer_ref' => (int) $customer->id,
                'exported_at' => CarbonImmutable::now()->toIso8601String(),
                'entries' => $trail->map(fn (ConsentTrailEntry $entry): array => [
                    'type' => $entry->type->value,
                    'channel' => $entry->channel->value,
                    'occurred_at' => $entry->occurredAt?->toIso8601String(),
                    'reason' => $entry->reason(),
                    'disclosure_version' => $entry->consentRecord?->disclosure_version,
                    'capture_surface' => $entry->consentRecord?->capture_surface?->value,
                    'proof' => $entry->consentRecord?->proof,
                ])->all(),
            ];

            $request = new DataRequest;
            $request->forceFill([
                'business_id' => $business->id,
                'business_ref' => $business->id,
                'kind' => DataRequestKind::ConsentAudit,
                'status' => DataRequestStatus::Fulfilled,
                'customer_ref' => (int) $customer->id,
                'due_at' => CarbonImmutable::now()->addDays($this->statutoryDueDays()),
                'requested_by' => $requester->id,
                'requested_at' => now(),
                'fulfilled_at' => now(),
                'detail' => $this->normaliseNote($detail),
                'outcome_note' => 'Consent trail produced through ConsentService::proofFor().',
                'result_payload' => $payload,
            ])->save();

            return $request;
        });
    }

    /**
     * File a tenant-wide export ask — the first of the two people.
     *
     * ⚠️ **DOES NOT BUILD ANYTHING YET.** Filing only dates the ask; a second
     * person approves it through {@see self::approveTenantExport()}, which is
     * what actually calls {@see ExportBuilder}. This is unrelated to the
     * owner's own "Download my data" button (`Livewire\Account\Settings`),
     * which calls `ExportBuilder::request()` directly and is never gated —
     * this queue exists for support acting *on a tenant's behalf*, and a
     * second person before staff can pull a whole account's data is the
     * control `28` §9.5 asks for, not friction on the tenant's own copy.
     */
    public function fileTenantExport(int $businessId, User $requester, ?string $detail = null): DataRequest
    {
        $business = $this->requireBusiness($businessId);

        $request = new DataRequest;
        $request->forceFill([
            'business_id' => $business->id,
            'business_ref' => $business->id,
            'kind' => DataRequestKind::TenantExport,
            'status' => DataRequestStatus::Open,
            'due_at' => CarbonImmutable::now()->addDays($this->statutoryDueDays()),
            'requested_by' => $requester->id,
            'requested_at' => now(),
            'detail' => $this->normaliseNote($detail),
        ])->save();

        return $request;
    }

    /**
     * @throws InvalidArgumentException when the id is gone
     */
    public function approveTenantExportById(int $id, User $approver): void
    {
        $request = DataRequest::query()->whereKey($id)->first();

        if (! $request instanceof DataRequest) {
            throw new InvalidArgumentException('That request is gone.');
        }

        $this->approveTenantExport($request, $approver);
    }

    /**
     * Second person agrees; dispatches {@see ExportBuilder} on the tenant's
     * behalf. The row stays `Open` — {@see self::noteExportOutcome()} is what
     * closes it, once the build actually finishes or fails.
     *
     * ## ⚠️ `audit_log` NAMES BOTH PEOPLE, BECAUSE IT IS A TWO-PERSON ACT (1910)
     *
     * `ExportBuilder::request()` records `export.requested` against whoever
     * called it, which on this path is the **approver** — so the append-only
     * trail read `["export.requested by user:10", "export.built by system"]` for
     * an action two people took, and `tenant_exports.requested_by` holds the
     * approver's id, which reads as the filer's. `29` §2 rule 42's trail showing
     * one name for a two-person control is the same defect class as a falsified
     * actor, one step milder.
     *
     * The fix is an entry rather than a column: `export.approved` names the
     * approver as actor and the filer in metadata, and the two ids are in one
     * row where an auditor is already looking. `tenant_exports.requested_by`
     * stays the approver deliberately — it means *"who caused this build"*,
     * which on the owner's own path is the owner and on this one is the second
     * person, and the `data_requests` row remains the record of who asked
     * (`TenantDeletion`'s "the request row is the record" reasoning, stated here
     * rather than assumed).
     *
     * @throws InvalidArgumentException on self-approve or wrong kind/status
     */
    public function approveTenantExport(DataRequest $request, User $approver): void
    {
        if ($request->kind !== DataRequestKind::TenantExport) {
            throw new InvalidArgumentException('Only a tenant-export request is approved this way.');
        }

        if ($request->status !== DataRequestStatus::Open) {
            throw new InvalidArgumentException('This export ask is not waiting on approval.');
        }

        if ((int) $request->requested_by === (int) $approver->id) {
            throw new InvalidArgumentException(
                '`28` §9.5 requires two people. The person who filed an export ask cannot approve it.'
            );
        }

        $business = $this->requireBusiness((int) $request->business_ref);

        Tenancy::actingAs((int) $business->id, function () use ($request, $approver, $business): void {
            // ⚠️ **THE APPROVAL IS RECORDED BEFORE THE BUILD IS DISPATCHED, AND
            // THE ORDER USED TO BE THE OTHER WAY** (2044).
            // {@see ExportBuilder::request()} dispatches `BuildTenantExportJob`
            // inside itself, and on the `sync` driver — the test configuration,
            // which is what makes this reproducible rather than theoretical —
            // the job runs to completion *inside* that call and writes its own
            // `outcome_note` through `noteExportOutcome()`. The `save()` that
            // used to follow then wrote `outcome_note` computed from a model
            // loaded before any of that happened, and the build's sentence, its
            // file counts and 1999's *"waiting for the tenant to collect it"*
            // were silently gone. On the database queue driver it is the same
            // clobber through a narrower window.
            //
            // The deeper reason to invert it is the one 1950 records on another
            // branch: an effect must not precede the write that records it. A
            // dispatch that succeeds while this `save()` throws would leave an
            // export built and handed over with no approval on the row that
            // authorised it — `28` §9.5's two-person rule with one of the two
            // unrecorded.
            //
            // ⚠️ The export id therefore leaves this sentence and stays where it
            // is durable: the `export.approved` entry below names the export as
            // its entity, and the build's own note names it in prose.
            $request->forceFill([
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'outcome_note' => $this->appendNote(
                    $request->outcome_note,
                    'Approved — building through ExportBuilder.',
                ),
            ])->save();

            $export = $this->exports->request($business, $approver, ExportSource::Ops, (int) $request->id);

            $this->audit->record(
                'export.approved',
                'user:'.$approver->id,
                $export,
                [
                    'data_request_id' => (int) $request->id,
                    // The other half of the two-person rule, in the same row.
                    'filed_by' => 'user:'.$request->requested_by,
                ],
            );
        });
    }

    /**
     * Close the loop once a build this queue approved finishes — called by
     * {@see BuildTenantExportJob} after {@see ExportBuilder::build()}
     * returns, never by `ExportBuilder` itself: that class has no dependency on
     * this one, and giving it one would make the two constructors circular
     * (`approveTenantExport()` above needs `ExportBuilder`, so `ExportBuilder`
     * cannot need this class back).
     *
     * A no-op for an export with no linked request (the owner's own button) or
     * a request already closed — the second guard matters because a rebuilt
     * export after a first success would otherwise try to re-fulfil a row
     * that is already done.
     */
    public function noteExportOutcome(TenantExport $export): void
    {
        if ($export->data_request_id === null) {
            return;
        }

        $request = DataRequest::query()->whereKey($export->data_request_id)->first();

        if (! $request instanceof DataRequest || $request->status->isClosed()) {
            return;
        }

        match ($export->status) {
            // ⚠️ `Built`, NEVER `Fulfilled` (1999). See {@see DataRequestStatus}
            // for why the difference is not pedantry: nothing in this
            // application can observe that the tenant received their data, so a
            // row asserting they did is a claim rather than a record.
            ExportStatus::Ready => $request->forceFill([
                'status' => DataRequestStatus::Built,
                'outcome_note' => $this->appendNote(
                    $request->outcome_note,
                    'Built — see tenant_exports #'.$export->id.'. '
                        .count((array) ($export->manifest['included'] ?? [])).' file(s) included, '
                        .count((array) ($export->manifest['excluded'] ?? [])).' refused and named in the manifest. '
                        .'Waiting for the tenant to collect it — the link is on their account page '
                        .'and an email was attempted.',
                ),
            ])->save(),
            ExportStatus::Failed => $request->forceFill([
                'status' => DataRequestStatus::Refused,
                'refused_at' => now(),
                'outcome_note' => $this->appendNote($request->outcome_note, 'Build failed: '.$export->failure_reason),
            ])->save(),
            default => null,
        };
    }

    /**
     * The tenant collected it — the only thing that closes a tenant-export ask
     * as `Fulfilled` (1999).
     *
     * Called by {@see TenantExportDownloadController} after its own audit
     * entry, on the same reasoning that puts {@see self::noteExportOutcome()}
     * on the job rather than inside `ExportBuilder`: the caller knows the
     * artifact actually left the building, and this class stays the only writer
     * of `data_requests`.
     *
     * ⚠️ **THE FETCH IS THE ONLY DELIVERY SIGNAL THIS APPLICATION HAS.** The
     * email relay vendor is not picked (1191–1195) and bounce handling is
     * unbuilt (open question H), so `announce()`'s mail proves nothing either
     * way. A row that closed on *build* asserted a delivery nobody observed;
     * this closes on the one event that is observed.
     *
     * A no-op for an export with no linked request (the owner's own button) and
     * for a row that is not sitting at `Built` — a second fetch of the same ZIP
     * must not re-stamp `fulfilled_at`, which would move the date a statutory
     * clock is read against.
     */
    public function noteExportFetched(TenantExport $export): void
    {
        if ($export->data_request_id === null) {
            return;
        }

        $request = DataRequest::query()->whereKey($export->data_request_id)->first();

        if (! $request instanceof DataRequest || $request->status !== DataRequestStatus::Built) {
            return;
        }

        $request->forceFill([
            'status' => DataRequestStatus::Fulfilled,
            'fulfilled_at' => now(),
            'outcome_note' => $this->appendNote(
                $request->outcome_note,
                'Collected by the tenant on '.CarbonImmutable::now()->toDayDateTimeString()
                    .' — see audit_log `export.downloaded` for who fetched it.',
            ),
        ])->save();
    }

    /**
     * Cancel an open / awaiting-deletion erasure and its TenantDeletion twin,
     * or an open tenant-export ask.
     *
     * ⚠️ **CANCELLING AN APPROVED EXPORT DOES NOT STOP A BUILD ALREADY
     * DISPATCHED.** Unlike erasure's cooling window, there is no window here to
     * cancel inside — `ExportBuilder::request()` dispatches the job in the same
     * call as approval. This only dismisses the queue's own tracking row;
     * {@see self::noteExportOutcome()} finds it already closed and leaves it
     * alone rather than overwriting a deliberate cancellation with a later
     * "built" note.
     */
    public function cancel(DataRequest $request, User $actor, ?string $reason = null): void
    {
        if ($request->status->isClosed()) {
            throw new InvalidArgumentException('This request is already closed.');
        }

        if (! in_array($request->kind, [DataRequestKind::Erasure, DataRequestKind::TenantExport], true)) {
            throw new InvalidArgumentException('Only erasure and tenant-export requests are cancelled this way.');
        }

        $business = $this->requireBusiness((int) $request->business_ref);

        Tenancy::actingAs((int) $business->id, function () use ($request, $actor, $reason, $business): void {
            // Only an erasure has a TenantDeletion twin to cancel alongside it —
            // asking pendingFor() for a tenant-export row would risk finding an
            // unrelated open erasure on the same account and cancelling it.
            $pending = $request->kind === DataRequestKind::Erasure
                ? $this->deletions->pendingFor($business)
                : null;

            DB::transaction(function () use ($request, $actor, $reason, $pending): void {
                if ($pending !== null) {
                    $this->deletions->cancel($pending, $actor, $reason);
                }

                $request->forceFill([
                    'status' => DataRequestStatus::Cancelled,
                    'cancelled_by' => $actor->id,
                    'cancelled_at' => now(),
                    // ⚠️ APPENDED, NEVER REPLACED. This assigned
                    // normaliseNote($reason) outright, and the reason box is
                    // optional — so cancelling an already-approved erasure with
                    // no reason typed set outcome_note to **null**, destroying
                    // the erasure gap note approve() had written. That note is
                    // the record of what an erasure did and did not reach
                    // (1380-1382), on a row whose whole purpose is to outlive
                    // the tenant it names. An audit trail that a later action
                    // can blank is not one.
                    'outcome_note' => $this->appendNote(
                        $request->outcome_note,
                        $this->normaliseNote($reason, 500),
                    ),
                ])->save();
            });
        });
    }

    /**
     * Mark erasures fulfilled once TenantDeletion has destroyed the account.
     *
     * Called from {@see ExecuteTenantDeletions} after a
     * successful execute — the queue must not claim "done" while the account
     * still exists, and must not sit `awaiting_deletion` forever after it goes.
     */
    public function noteErasureExecuted(int $businessRef): void
    {
        DataRequest::query()
            ->where('business_ref', $businessRef)
            ->where('kind', DataRequestKind::Erasure)
            ->where('status', DataRequestStatus::AwaitingDeletion)
            ->update([
                'status' => DataRequestStatus::Fulfilled->value,
                'fulfilled_at' => now(),
                'outcome_note' => $this->erasureGapNote(),
                'updated_at' => now(),
            ]);
    }

    private function openErasureFor(Business $business): ?DataRequest
    {
        return DataRequest::query()
            ->where('business_ref', $business->id)
            ->where('kind', DataRequestKind::Erasure)
            ->whereIn('status', [
                DataRequestStatus::Open->value,
                DataRequestStatus::AwaitingDeletion->value,
            ])
            ->orderBy('id')
            ->first();
    }

    private function assertOpenErasure(DataRequest $request): void
    {
        if ($request->kind !== DataRequestKind::Erasure) {
            throw new InvalidArgumentException('Only an erasure request can be approved this way.');
        }

        if ($request->status !== DataRequestStatus::Open) {
            throw new InvalidArgumentException('This erasure is not waiting on approval.');
        }
    }

    private function requireBusiness(int $businessId): Business
    {
        $business = $this->accounts->business($businessId);

        if (! $business instanceof Business) {
            throw new InvalidArgumentException('That account no longer exists.');
        }

        return $business;
    }

    /**
     * What this platform records about an erasure it has performed.
     *
     * ⛔ **THIS SENTENCE HAS NOW BEEN HAND-CORRECTED TWICE, IN THE SAME
     * DIRECTION, AND GONE STALE TWICE — SO IT NO LONGER CARRIES A LIST**
     * (8861). It said *"Nothing removed from R2 (1382)"* after 1902 gave
     * exports a real purge; 5080 replaced that with *"Object-store data (tenant
     * export ZIPs and the pixel archive) is purged before the account is
     * destroyed"*, and the parenthesis reads as an illustration of a complete
     * removal. It is not one: {@see StoredObjectKind} names five
     * kinds of object this application puts in a bucket for a tenant, and **on
     * 2026-08-23 an erasure purged exactly one of them** — a reading with a
     * date, deliberately, because that is the number the two previous
     * corrections each wrote down without one. Two of the four it left are a
     * photograph a member of the public texted a business and a member of the
     * public's recorded voice. **Both corrections understated what survived,
     * and each was true on the day it was written.**
     *
     * ⛔ **THE REMEDY IS NOT A THIRD, LONGER LIST.** The purged set lives in
     * {@see TenantDeletion::execute()} and moves without this method moving —
     * that is the whole mechanism by which the previous two went stale, and a
     * paragraph that accurately describes a hazard is what stops the next
     * reviewer looking (314-316). **A list here is refused**, and
     * `tests/Feature/DataRequestQueueTest.php` enforces the refusal against
     * {@see StoredObjectKind}'s own cases, so a sixth kind cannot
     * make this note stale — it never named the fifth.
     *
     * ⚠️ **AND EVERY INVENTORY AVAILABLE HERE IS ITSELF INCOMPLETE, WHICH IS
     * WHY DERIVING ONE WOULD HAVE UNDERSTATED FOR A THIRD TIME** (8863).
     * `StoredObjectKind` is a measurement vocabulary — its own docblock says so
     * — and on 2026-08-23 the pixel L0 archive that `execute()` genuinely did
     * purge was not one of its cases. A note generated from it would have named
     * five kinds and missed the one the old sentence got right.
     *
     * ⚠️ **THE CRYPTO-SHRED AND OWNER-ROW CLAUSES ARE UNCHANGED AND STILL
     * ACCURATE** (1380, 1381). They are properties of the method rather than
     * enumerations of a set, which is why they have not gone stale and the
     * third clause did.
     *
     * ⚠️ **WHO READS THIS.** One render site,
     * `resources/views/livewire/support/data-request-queue.blade.php`, behind
     * `SupportAccess::GATE`. It is not emailed and not in the tenant export, so
     * no data subject ever sees it — it is this platform's own account of what
     * an erasure did, and the artefact anybody answering a regulator would
     * read. ⚠️ **Naming the surviving objects here would hand a support
     * operator a map to the residual personal data of somebody who asked to be
     * forgotten** (lesson 18); naming nothing hands them a wrong answer. The
     * ruling names neither, and points at the code that can be read at the
     * version that ran.
     *
     * ⚠️ **KEEP IT SHORT.** {@see self::appendNote()} truncates at 2,000
     * characters, and a cancellation reason is appended after this.
     */
    private function erasureGapNote(): string
    {
        return 'Fulfilled through TenantDeletion (delete-plus-survivors). '
            .'Not crypto-shred — there is no per-identity DEK (decision 1380). '
            .'Owner users row untouched (1381). '
            .'Object-store purges run before the account is destroyed and refuse '
            .'the whole deletion if one fails (1902, 5080). '
            .'This note lists neither what they reached nor what they missed, by '
            .'ruling 8861: what an erasure purges is a property of the code that '
            .'ran on the day rather than of this row, and both lists written here '
            .'before it read as complete when they were not. TenantDeletion at the '
            .'deployed version is the authority. '
            .'What is true whatever they reached: an object no purge reaches '
            .'outlives the row that named it, and retention pruning is row-driven '
            .'and tenant-scoped, so an erased account\'s remaining objects are kept '
            .'where a live account\'s are pruned on a period (8862).';
    }

    /**
     * Turn the database's answer to a duplicate filing into the same message the
     * read-then-write guard gives.
     *
     * ⚠️ **THE GUARD ABOVE IS A READ FOLLOWED BY A WRITE, SO IT HAS A WINDOW.**
     * Two support staff filing an erasure for the same account at once both see
     * no open request, both proceed, and the partial unique index — correctly —
     * refuses the second. Untranslated that surfaced as a `QueryException`, past
     * the caller's `InvalidArgumentException` catch, as a 500 on a queue screen:
     * the right outcome delivered as a crash. The index is the real guard and
     * stays that way; this only makes the loser's message match the winner's.
     *
     * `23505` is Postgres' unique_violation. Anything else is re-thrown
     * untouched — swallowing every QueryException here would hide real ones.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $file
     * @return TReturn
     */
    private function translatingDuplicate(callable $file): mixed
    {
        try {
            return $file();
        } catch (QueryException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }

            throw new InvalidArgumentException(
                'This account already has an open deletion request. Cancel it before filing another.',
                previous: $e,
            );
        }
    }

    /**
     * Add to a request's outcome note without losing what is already there.
     *
     * `outcome_note` accumulates: each thing that happened to a request adds a
     * line, and nothing removes one. Both sides are already normalised by the
     * caller, so a null on either side simply yields the other.
     */
    private function appendNote(?string $existing, ?string $addition): ?string
    {
        if ($existing === null || $existing === '') {
            return $addition;
        }

        if ($addition === null || $addition === '') {
            return $existing;
        }

        return mb_substr($existing."\n".$addition, 0, 2000);
    }

    private function normaliseNote(?string $note, int $limit = 2000): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);

        return $note === '' ? null : mb_substr($note, 0, $limit);
    }
}
