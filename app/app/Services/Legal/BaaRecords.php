<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Enums\BaaStatus;
use App\Enums\LegalDocumentType;
use App\Models\BaaRecord;
use App\Models\Business;
use App\Models\LegalDocument;
use App\Services\AuditService;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one place a Business Associate Agreement is opened, executed or revoked.
 *
 * ⚠️ WHY THIS EXISTS. `29` §2 rule 24 holds a PHI tenant's form values at
 * `schema_only` "until the BAA is executed", and that phrase had no state
 * anywhere in this codebase. `LegalDocumentType::Baa` versions the *template* —
 * counsel drafts and publishes it exactly like the other twelve documents
 * (decision 420) — and nothing recorded an execution against a named business.
 * So rule 24's condition was unanswerable and `29` §12.2's prelaunch gate
 * ("healthcare-counsel review of the BAA template before the first PHI tenant")
 * had nothing enforcing it.
 *
 * Same shape as `LegalDocuments`, `DestinationSettings` and `FeedbackPages`, and
 * enforced the same way: an `ArchitectureTest` lint names the files allowed to
 * touch `BaaRecord` at all. A screen writing its own row would bypass the rules
 * below, and the one that matters cannot be repaired afterwards — a recorded
 * execution against words counsel never reviewed is a claim we would then be
 * making to a covered entity.
 *
 * ⚠️ WHAT THE EXECUTION GATE ACTUALLY ENFORCES, WHICH IS LESS THAN "COUNSEL
 * REVIEWED IT". `recordExecution()` refuses any version that is not the BAA
 * document type, not published, still marked a placeholder, or published after
 * the date the tenant signed. Behind publication, `LegalDocuments::publish()`
 * refuses a version with no `reviewed_at`, and `recordReview()` refuses an empty
 * reviewer name. So the enforced chain is: **a named reviewer is recorded, the
 * text is not a placeholder, the version is published, and it existed on the day
 * it was signed.**
 *
 * ⚠️ IT IS NOT PROOF THAT HEALTHCARE COUNSEL REVIEWED ANYTHING. `Admin\
 * LegalDocuments` puts the reviewer name, the placeholder checkbox and the
 * publish button in front of one staff user in one session; nothing binds that
 * name to anybody outside the company and nothing separates reviewer from
 * publisher. `29` §12.2 gate 2 — healthcare-counsel review of the BAA template
 * before the first PHI tenant — is a **human** prelaunch gate that this code
 * cannot verify, and describing these refusals as that gate is what stops the
 * next reviewer looking for the gate itself (314–316). `39` seeds every legal
 * draft as a placeholder at `0.9`, so the placeholder refusal is what keeps a
 * covered entity off filler text; that is worth having and it is not counsel
 * review.
 *
 * ⚠️ AN EXECUTED BAA HERE SAYS NOTHING ABOUT OUR OWN VENDORS. It makes *us* the
 * tenant's Business Associate. It does not make Anthropic or OpenAI ours —
 * `docs/SUBPROCESSOR-INVENTORY.md` records that no BAA exists with either — so
 * `isExecutedFor()` must never be wired into the AI gate of decisions 421–423.
 * `tests/Feature/PhiAnalysisGateTest.php` pins that as a tripwire.
 *
 * ⚠️ EVERY METHOD RUNS AS THE BUSINESS IT NAMES, AND NONE OF THEM SWITCHES
 * TENANT FOR YOU. `AuditService::record()` opens with `Tenancy::idOrFail()`
 * (decision 419), so an admin acting on a specific business must wrap the call
 * in `Tenancy::actingAs($business->id, …)` — filing it under whatever tenant the
 * admin happened to be viewing would bury the record in the wrong log. This
 * class asserts that rather than doing it, because a service that silently
 * re-points the tenant is a service that hands every future caller cross-tenant
 * write access without saying so. The one screen that needs that power spells
 * it out.
 *
 * ⚠️ RE-EXECUTION AFTER REVOCATION IS NOT BUILT. `open()` returns the existing
 * record whatever its state, and `recordExecution()` refuses anything but a
 * pending one, so a revoked agreement is terminal until somebody decides
 * whether its successor is a new row or a resurrection. Nothing in this phase
 * needs it and the owner has not ruled; building a guess would make that ruling
 * for them.
 */
final class BaaRecords
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Open the agreement, or return the one already open.
     *
     * Idempotent because its callers are: `TenantProvisioner` opens one when a
     * business provisions as PHI, and `TenantClassification::reclassify()` opens
     * one when a business is raised to PHI later. A tenant that arrived as a
     * dental practice and is then reclassified must not end up with two records,
     * because "which one is the agreement" has no answer.
     *
     * ⚠️ THE IDEMPOTENCY IS ENFORCED BY A UNIQUE INDEX, NOT BY THIS LOOKUP. The
     * read below is a SELECT and the write is an INSERT, so two simultaneous
     * calls both see nothing and both insert — decision 350's shape, and the
     * consequence here is worse than a duplicate row: `forBusiness()` takes the
     * newest by id, so a second *pending* row landing after an executed one
     * makes `isExecutedFor()` answer **false for a tenant that has signed**,
     * with the executed row sitting right there. `baa_records` therefore carries
     * `unique(business_id)`, so the racing insert fails closed with SQLSTATE
     * 23505 rather than quietly answering the wrong question afterwards. One row
     * per business is the invariant, and re-execution after revocation is not
     * built (see the class docblock), so it is not a temporary one.
     *
     * ⚠️ AND LOSING THAT RACE IS AN OUTCOME, NOT AN ERROR. "Fails closed" was
     * true of the row and false of the caller: `Admin\PhiTenants::act()` catches
     * `LogicException`, while `UniqueConstraintViolationException` descends from
     * `PDOException` → `RuntimeException`, so two staff on the same business —
     * or provisioning racing a reclassification — reached a compliance screen as
     * an unhandled 500. The catch below restores the documented idempotency: the
     * other transaction won, its row *is* the agreement, and returning it is
     * exactly what a caller arriving one moment later would have got.
     *
     * ⚠️ THE CATCH SITS OUTSIDE `DB::transaction()`, DELIBERATELY. Postgres
     * aborts a transaction on a failed statement, so nothing may run on that
     * connection until it is rolled back — `DB::transaction()` has already
     * rolled back to its savepoint by the time the exception leaves it, and the
     * re-read below is on a usable connection. Inside the closure it would be a
     * second failure with a `25P02` and no explanation. This matters more than
     * it looks: `TenantClassification::reclassify()` calls this from inside its
     * own transaction, which `TenantProvisioner` in turn calls from inside
     * registration's (271), so the savepoint being rolled back is three levels
     * down.
     *
     * ⚠️ ONLY FOR A PHI TENANT. `TenantProvisioner` states the reason and this is
     * where it has to hold: a pending record on every business would make "has a
     * BAA record" meaningless, and meaningless is what the next reader trusts.
     * The screen renders its forms inside a classification check, but a Livewire
     * action is not gated by what rendered it — `$businessId` is a public
     * property and the action is callable regardless (decision 391's shape).
     *
     * ⚠️ `->latest('id')`, NEVER `created_at`. Postgres sorts NULL first on a
     * DESC ordering and `timestamps()` leaves the column nullable, so ordering on
     * it would put an undated row ahead of every dated one. An ArchitectureTest
     * lint fails the build on any other column.
     *
     * @throws InvalidArgumentException when the business is not the tenant in
     *                                  context, or does not handle health
     *                                  information
     */
    public function open(Business $business): BaaRecord
    {
        $this->assertIsTenant((int) $business->id);

        if (! $business->data_classification->requiresPhiIsolation()) {
            throw new InvalidArgumentException(
                'This business does not handle health information, so it needs no Business '
                .'Associate Agreement. Move it to health-information handling first — a '
                .'pending record on every business would make "has a BAA record" mean nothing.'
            );
        }

        $existing = $this->forBusiness($business);

        if ($existing instanceof BaaRecord) {
            return $existing;
        }

        try {
            // ONE TRANSACTION, because the audit entry is the record of the act
            // and a row with no entry cannot be explained afterwards.
            // `PlaceConfirmation::confirm()`'s precedent, and the reason it is
            // not left to the caller: `TenantProvisioner` runs inside
            // registration's transaction (271) and the admin screen has no
            // wrapper at all.
            return DB::transaction(function () use ($business): BaaRecord {
                $record = new BaaRecord;

                // forceFill(), because every meaningful column on this model is
                // guarded: this service is the only writer and the guard is what
                // keeps a request-shaped payload from declaring an agreement
                // executed.
                $record->forceFill([
                    'business_id' => $business->id,
                    'status' => BaaStatus::Pending,
                ])->save();

                // ⚠️ THE ACTOR IS 'system' EVEN WHEN AN ADMIN CAUSED IT, and that is
                // accurate rather than lazy: nobody decides to open a BAA. It opens as a
                // consequence of a business becoming a covered entity, by provisioning
                // or by reclassification — and the *decision*, when there was one, is
                // audited under the person who made it as `business.reclassified`.
                // Naming an admin here would claim they performed an act they did not.
                $this->audit->record('baa.opened', 'system', $record, [
                    'status' => BaaStatus::Pending->value,
                ]);

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            // The other transaction won; its row is the agreement. Ours rolled
            // back whole — row and audit entry together — so nothing here
            // recorded an opening that did not happen.
            //
            // The `??` is not defensive padding. It is reachable if the unique
            // index is ever widened past `business_id` alone, and it fails with
            // a sentence rather than by returning something that is not this
            // business's agreement.
            return $this->forBusiness($business) ?? throw new InvalidArgumentException(
                'Another request opened this business\'s Business Associate Agreement at the '
                .'same moment, and it could not then be read back. Nothing was recorded twice; '
                .'try again.'
            );
        }
    }

    /**
     * Record that both sides signed one exact version of the template.
     *
     * @param  string  $actor  Who recorded it — 'user:14', never a bare name.
     *
     * @throws InvalidArgumentException when the version is not a published,
     *                                  final BAA that existed on the signing
     *                                  date, when the record is not pending, or
     *                                  when a signature is unnamed
     */
    public function recordExecution(
        BaaRecord $record,
        LegalDocument $version,
        string $tenantSigner,
        string $tenantTitle,
        CarbonImmutable $tenantSignedAt,
        string $goaiezSigner,
        string $actor,
    ): BaaRecord {
        $this->assertIsTenant((int) $record->business_id);

        // ⚠️ RE-CHECKED HERE EVEN THOUGH THE SCREEN CHECKS FIRST. The caller
        // resolves the version from the signing date and calls this same method
        // before opening a record; this call is what makes the write path fail
        // closed independently of whoever resolved the version (314–316), and
        // it is the only layer a queued importer or a repair script would meet.
        $this->refuseUnusableVersion($version, $tenantSignedAt);

        if ($record->status !== BaaStatus::Pending) {
            throw new InvalidArgumentException(
                'This agreement is already '.$record->status->label().'. An execution is '
                .'recorded once — a later change is a new agreement, not an edit to this one.'
            );
        }

        // A signature is a named human on both sides. "The practice" and
        // "GO AI EZ" are parties, not signatories, and a record that cannot say
        // who signed cannot be produced as evidence that anybody did.
        $tenantSigner = trim($tenantSigner);
        $goaiezSigner = trim($goaiezSigner);

        if ($tenantSigner === '' || $goaiezSigner === '') {
            throw new InvalidArgumentException(
                'Both signatures need a named person. A signature nobody is named for '
                .'is not evidence that the agreement was executed.'
            );
        }

        return DB::transaction(function () use (
            $record,
            $version,
            $tenantSigner,
            $tenantTitle,
            $tenantSignedAt,
            $goaiezSigner,
            $actor,
        ): BaaRecord {
            $record->forceFill([
                'legal_document_id' => $version->id,
                'status' => BaaStatus::Executed,
                'tenant_signed_at' => $tenantSignedAt,
                'tenant_signer_name' => $tenantSigner,
                'tenant_signer_title' => trim($tenantTitle),
                'goaiez_signed_at' => now(),
                'goaiez_signer' => $goaiezSigner,
            ])->save();

            // ⚠️ NO SIGNER NAMES IN THE AUDIT METADATA, DELIBERATELY. They are PII of
            // named individuals and they already live on the row this entry points
            // at, so copying them here would store the same personal data twice —
            // once in a table designed to be append-only forever. What an auditor
            // needs from this entry is *which version* was executed and *who
            // recorded it*; the names are one join away, behind the same tenant
            // boundary.
            //
            // ⚠️ AND THE ASYMMETRY THAT LEAVES, SAID OUT LOUD. `revoke()` and
            // `TenantClassification::reclassify()` both write an operator's free
            // text straight into that same append-only table, and an operator
            // types *"Dr Jane Smith confirmed by phone"*. Keeping names out here
            // narrows the surface; it does not close it, and the reason there is
            // no scrubber is that a reason nobody can read is not a reason. If
            // that trade is ever revisited, revisit all three writers together.
            $this->audit->record('baa.executed', $actor, $record, [
                'legal_document_id' => $version->id,
                'version' => $version->version,
            ]);

            return $record;
        });
    }

    /**
     * End the agreement.
     *
     * The reason is required and typed, because a revocation with no reason
     * cannot answer the only question it is ever asked — whether this tenant's
     * PHI handling stopped because the practice closed or because something went
     * wrong.
     *
     * ⚠️ ONLY AN EXECUTED AGREEMENT MAY BE ENDED, AND BOTH REFUSALS ARE REAL
     * LOSSES RATHER THAN TIDINESS:
     *
     *   pending  ending one is a permanent lockout. `open()` returns the
     *            existing row whatever its state and `recordExecution()` refuses
     *            anything but a pending one, so a revoked-while-pending tenant
     *            can never have a BAA recorded at all — and re-execution after
     *            revocation is not built (see the class docblock).
     *   revoked  a second call overwrites `revoked_at` and `revoke_reason`,
     *            destroying the answer to the only question a revocation is
     *            kept to answer: was this agreement in force on the day that
     *            record was written.
     *
     * ⚠️ THE REASON IS OPERATOR FREE TEXT AND LANDS IN AN APPEND-ONLY TABLE.
     * Somebody will type a person's name into it. See `recordExecution()`'s note
     * on the asymmetry that leaves; the input is length-bounded at the screen
     * and nothing scrubs it, because a reason nobody can read is not a reason.
     *
     * @throws InvalidArgumentException when the reason is empty or the agreement
     *                                  is not in force
     */
    public function revoke(BaaRecord $record, string $reason, string $actor): BaaRecord
    {
        $this->assertIsTenant((int) $record->business_id);

        if ($record->status !== BaaStatus::Executed) {
            throw new InvalidArgumentException(
                $record->status === BaaStatus::Pending
                    ? 'This agreement was never signed, so there is nothing to end — and ending '
                        .'it would lock this tenant out permanently, because an execution can '
                        .'only be recorded against a pending agreement.'
                    : 'This agreement has already ended. Recording a second ending would '
                        .'overwrite the date and reason of the first, which is the only record '
                        .'of when it stopped.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'Ending an agreement needs a reason. This row is the record of why it ended.'
            );
        }

        return DB::transaction(function () use ($record, $reason, $actor): BaaRecord {
            $record->forceFill([
                'status' => BaaStatus::Revoked,
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ])->save();

            $this->audit->record('baa.revoked', $actor, $record, [
                'reason' => $reason,
            ]);

            return $record;
        });
    }

    /**
     * Whether rule 24's "until the BAA is executed" is satisfied for a business.
     *
     * ⚠️ FAILS CLOSED IN ALL THREE DIRECTIONS. No record, a pending record and a
     * revoked record are each `false`, and the last is the one worth stating:
     * "has a BAA row" and "has a BAA in force" are different questions, and the
     * plausible shorthand — a `whereNotNull` or a `!== Pending` — answers the
     * first while reading like the second.
     */
    public function isExecutedFor(Business $business): bool
    {
        $this->assertIsTenant((int) $business->id);

        return $this->forBusiness($business)?->isInForce() === true;
    }

    /**
     * The business's agreement, whatever state it is in.
     */
    public function forBusiness(Business $business): ?BaaRecord
    {
        $this->assertIsTenant((int) $business->id);

        // The global scope already constrains this to the tenant asserted above;
        // `->latest('id')` is the ordering, and `id` is the only column this
        // codebase may order descending on — see open()'s docblock.
        return BaaRecord::query()->latest('id')->first();
    }

    /**
     * Refuse a version that cannot lawfully be executed against, for a signature
     * dated on that day.
     *
     * ⚠️ PUBLIC SO A CALLER CAN REFUSE **BEFORE** IT OPENS A RECORD. `open()`
     * writes a row and a `baa.opened` audit entry; calling it as an argument to
     * `recordExecution()` evaluates it first, so an execution refused for its
     * version still left a pending record and an audit line behind it. A caller
     * asks here, then opens. `recordExecution()` asks again regardless — this is
     * a courtesy to the caller, never the boundary.
     *
     * @throws InvalidArgumentException
     */
    public function refuseUnusableVersion(LegalDocument $version, CarbonImmutable $tenantSignedAt): void
    {
        if ($version->doc_type !== LegalDocumentType::Baa) {
            throw new InvalidArgumentException(
                'That version is the '.$version->doc_type->title().', not the Business '
                .'Associate Agreement. An execution names the exact document the tenant signed.'
            );
        }

        if (! $version->isPublished()) {
            throw new InvalidArgumentException(
                "Version {$version->version} of the Business Associate Agreement is still a "
                .'draft. A draft can still change, so nothing executed against it would stay '
                .'true — publish it first, which also requires a named reviewer.'
            );
        }

        if ($version->is_placeholder) {
            throw new InvalidArgumentException(
                "Version {$version->version} of the Business Associate Agreement is marked as "
                .'a working draft, not final text. `29` §12.2 puts healthcare-counsel review '
                .'before the first PHI tenant: record a reviewer and publish a final version '
                .'before executing against it.'
            );
        }

        // ⚠️ THE VERSION HAS TO HAVE EXISTED ON THE DAY THEY SIGNED. This is the
        // one refusal that is invisible until a second version is published:
        // publish v1.1 on Monday, record a paper signature dated the week
        // before, and the row would name v1.1 — asserting that a covered entity
        // agreed to words nobody had written yet. The type, publication and
        // placeholder checks all pass on that row, which is why the message
        // above them ("an execution names the exact document the tenant signed")
        // was true of the code and not of the screen calling it.
        //
        // Compared by day rather than by instant, for the reason
        // `LegalDocuments::currentAsOf()` gives: a version published at 10:00 on
        // the day somebody signed is a version they could have signed, and the
        // signing date carries no time.
        if ($version->published_at?->startOfDay()->greaterThan($tenantSignedAt->startOfDay()) === true) {
            throw new InvalidArgumentException(
                "Version {$version->version} of the Business Associate Agreement was published "
                .'on '.$version->published_at->format('j F Y').', after the '
                .$tenantSignedAt->format('j F Y').' this signature is dated. An execution names '
                .'the exact words the tenant signed, so it cannot name a version that did not '
                .'exist yet — check the date they signed, or record it against the version they '
                .'were given.'
            );
        }
    }

    /**
     * Refuse to act on a business that is not the tenant in context.
     *
     * ReviewRouter's guard, one table over. Every write here fills
     * `business_id` from ambient context and every audit row is filed against
     * whatever tenant is established — so acting on business A while business B
     * is in context would either be refused by RLS with a SQLSTATE or, on the
     * read path, quietly answer about the wrong business. That is the *wrong
     * tenant* case CLAUDE.md says row-level security cannot catch.
     *
     * @throws InvalidArgumentException
     */
    private function assertIsTenant(int $businessId): void
    {
        if ($businessId === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That business is not the tenant in context. A BAA record and its audit entry '
            .'are both filed against the acting business, so this would record one tenant\'s '
            .'agreement under another tenant\'s name. Wrap the call in Tenancy::actingAs().'
        );
    }
}
