<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CreditKind;
use App\Enums\CreditPool;
use App\Models\CreditLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default `business_id` — `BelongsToTenant` fills it from the tenant in
 * context, the same as `AuditLogEntryFactory` (see `LocationFactory`).
 *
 * ⚠️ **A FACTORY ROW IS NOT A COHERENT LEDGER.** It sets `delta` and
 * `balance_after` to one self-consistent pair and knows nothing about any row
 * before it, so two `create()` calls produce a history whose running total does not
 * add up. That is fine for testing the model, the CHECK constraints and isolation —
 * and wrong for testing the balance, which must be built through
 * `App\Services\Billing\CreditLedger::record()`, the only thing that computes
 * `balance_after` under a lock.
 *
 * `Factory` wraps creation in `Model::unguarded()`, which is why it can set
 * `balance_after` at all when the model guards it against callers.
 *
 * @extends Factory<CreditLedgerEntry>
 */
final class CreditLedgerEntryFactory extends Factory
{
    protected $model = CreditLedgerEntry::class;

    // No `@return array<string, mixed>` docblock: Laravel's stub generates one and
    // Larastan rejects it, because the parent declares the narrower
    // `array<model property of CreditLedgerEntry, mixed>`. Every other factory here
    // omits it for the same reason.
    public function definition(): array
    {
        return [
            'delta' => 100,
            'balance_after' => 100,
            'kind' => CreditKind::Purchase,
            // Stated rather than left to the column default, and it has to agree
            // with `kind`: a `purchase` outside the top-up pool is refused by
            // `credit_ledger_purchase_is_top_up` (3307). A `->monthly()` state
            // belongs beside this the day a test needs one — it does not exist yet
            // because the grant path builds its rows through
            // `App\Services\Billing\CreditLedger`, which is the only thing that
            // computes a coherent per-pool running total.
            'pool' => CreditPool::TopUp,
            'ref_type' => null,
            'ref_id' => null,
            'reason' => null,
            'created_by' => 'system',
        ];
    }
}
