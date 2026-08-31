<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\CustomerMergeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One contact folded into another, and everything needed to put it back
 * (`34` §1.2, `34` §7's build-failing *"merge is undoable 30 days"*).
 *
 * Written and read only through `App\Services\Crm\CustomerMerges` — a lint in
 * `tests/Feature/Architecture/CrmTest.php` holds it there, beside `CrmNote`'s
 * and `CrmTask`'s. The mistake it catches is the next screen wanting to show
 * "merged with" and growing its own query: the undo window, the live-versus-
 * undone predicate and the identifier restore order would each drift, and none
 * of the three reads as a bug on its own diff.
 *
 * ⚠️ **`changes` IS THE ONLY COPY OF WHAT THE MERGED-AWAY ROW HELD**, not a
 * convenience log — see the creating migration. Treat it the way the credential
 * store treats its ciphertext: nothing may rewrite it after the merge, because
 * the undo reads it and nothing else does.
 *
 * ⛔ **THAT PARAGRAPH WAS AN ASSERTION AND NOT A MECHANISM UNTIL 2026-08-23, AND
 * IT IS KEPT BECAUSE THE RULE IS UNCHANGED — WHAT CHANGED IS THAT SOMETHING NOW
 * ENFORCES IT** (8320). This model had no `booted()` at all: no `updating`
 * guard and no `deleting` guard, unlike `InboundMessage`, `ConsentRecord`
 * and `TermsAcceptance`, which all throw. **And the
 * suite proved the model permitted a rewrite** — `CustomerRegionTest` did
 * `$merge->changes = $changes; $merge->save();` and was green. So the sentence
 * a reader checks first said the blob was frozen, in the file where they would
 * look for the freeze, which is 314–316's shape: *the paragraph explaining the
 * hazard is what stops the next reviewer looking for the instance.*
 *
 * ⚠️ **WHAT THE GUARD PERMITS IS THE UNDO STAMP, BECAUSE `undo()` IS ITSELF AN
 * UPDATE OF THIS ROW** — see `self::MUTABLE`. A blanket `updating` refusal
 * would have been wrong and would have broken the one write this table exists
 * to serve.
 *
 * ⚠️ **AND WHAT IT DOES NOT REACH IS A QUERY BUILDER**. `DB::table
 * ('customer_merges')->update(…)` and `->delete()` fire no model event, which
 * is the gap every append-only table in this schema carries; the lint in
 * `tests/Feature/CustomerMergeTest.php` — *"a merge record is never reached by
 * the raw route"* — is what watches that path, on `ConsentTest`'s precedent for
 * `opt_outs`, `suppression_lifts` and `suppression_list`.
 *
 * @property array<string, mixed> $changes
 * @property Carbon $merged_at
 * @property ?Carbon $undone_at
 */
final class CustomerMerge extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CustomerMergeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * The columns an update may touch — the undo stamp, and nothing else.
     *
     * ⚠️ **AN ALLOWLIST RATHER THAN A BLANKET REFUSAL, BECAUSE `undo()` IS
     * ITSELF AN UPDATE OF THIS ROW.** `CustomerMerges::undo()` sets `undone_at`
     * and `undone_by` and saves; a `self::updating(fn () => throw …)` of
     * `InboundMessage`'s shape would have frozen the one write the thirty-day
     * promise is made of. **What a guard has to permit is established from the
     * writers before it is written**, and there are exactly two: the insert in
     * `merge()` and this stamp.
     *
     * ⚠️ **`updated_at` IS DELIBERATELY ABSENT AND ITS ABSENCE IS VERIFIED
     * RATHER THAN ASSUMED.** `Model::performUpdate()` fires `updating` **before**
     * it calls `updateTimestamps()`, so the timestamp is not yet dirty when this
     * runs — listing it would be an allowlist entry with no instance, which is
     * the shape this codebase keeps finding at column level. Setting it by hand
     * is refused, which is right: touching a merge's `updated_at` changes
     * nothing anybody reads.
     *
     * ⚠️ **`business_id` IS COVERED BY THIS AND NOT BY `$guarded`.** Guarding
     * stops `fill()` and `create()`; it says nothing about
     * `$merge->business_id = …; $merge->save();`, which would move a change set
     * holding two of one tenant's contacts into another tenant's account.
     *
     * @var list<string>
     */
    private const array MUTABLE = ['undone_at', 'undone_by'];

    protected static function booted(): void
    {
        self::updating(function (self $merge): void {
            // ⚠️ **AN UNDONE MERGE IS FINISHED, AND THE DIRECTION IS THE POINT.**
            // The stamp is one-way: `undo()` refuses a merge that is not live,
            // and a contact merged again gets a **new** row (the partial unique
            // index `WHERE undone_at IS NULL` is what makes that safe). Clearing
            // `undone_at` by hand would re-arm an undo that has already run, and
            // running it twice writes the change set's stale identifiers over
            // whatever the two contacts hold today — a silent revert of every
            // edit made since, wearing the label of a restore.
            if ($merge->getOriginal('undone_at') !== null) {
                throw new LogicException(
                    'This merge has already been undone, and an undone merge is finished. '
                    .'Clearing undone_at would re-arm an undo that has already run, and running '
                    .'it a second time would write the change set\'s old email and phone over '
                    .'whatever those two contacts hold now. Merge them again if they are the '
                    .'same person; that is a new row.'
                );
            }

            $frozen = array_diff(array_keys($merge->getDirty()), self::MUTABLE);

            if ($frozen !== []) {
                throw new LogicException(
                    'customer_merges is frozen except for the undo stamp, and changes is the only '
                    .'copy of what both contacts held before the merge — the merged-away row\'s '
                    .'email and phone are cleared by it. Rewriting '.implode(', ', $frozen).' '
                    .'would make the undo restore something neither contact ever had, or blank '
                    .'one instead of putting it back. Undo the merge and merge again.'
                );
            }
        });

        self::deleting(function (): never {
            // ⛔ **AND THE HORIZON IS NOT THIS MODEL'S TO TAKE.** The creating
            // migration refuses one in writing — *"a prune job would delete the
            // account of a merge on the day it became permanent, which is
            // exactly the day it starts mattering"* — and 8059(b)/8064(b) put
            // the retention question to the owner, unanswered. 8045's rule
            // applies here exactly: a delete against a `deleting`-guarded row is
            // indistinguishable on a diff from the abuse the guard exists to
            // catch, so nothing may prune this table until somebody rules.
            throw new LogicException(
                'customer_merges cannot be deleted. Inside the undo window the row is the only '
                .'copy of both contacts\' email and phone, so deleting it destroys a reversal the '
                .'owner was promised; outside it, the row is the record of what was done and by '
                .'whom, and a merge becoming permanent is exactly when that starts mattering. '
                .'A retention period for this table is an owner ruling that has not been made.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'merged_at' => 'datetime',
            'undone_at' => 'datetime',
        ];
    }

    /**
     * Whether this merge still stands.
     *
     * A one-line predicate rather than a scope, because the service is the only
     * caller and a scope on a chokepointed model is an invitation to query it
     * from somewhere else.
     */
    public function isLive(): bool
    {
        return $this->undone_at === null;
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function survivor(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'survivor_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function merged(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'merged_id');
    }
}
