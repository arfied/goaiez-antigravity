<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageCostKind;
use App\Models\MessageCostEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Does NOT default `business_id` — `BelongsToTenant` fills it from the tenant in
 * context, the same as every other tenant-owned factory here.
 *
 * ⚠️ **THE COST IS AN ARBITRARY NON-ZERO INTEGER OF MILLICENTS AND CARRIES NO
 * MEANING.** R9's money-number law says the true cost is never hardcoded; a
 * factory default that looked like a real provider rate would be a hardcoded
 * rate living in the one place nobody greps for prices. This one is deliberately
 * not a plausible SMS or email price, so that a test asserting against it has to
 * state its own figure.
 *
 * `Factory` wraps creation in `Model::unguarded()`, which is why it can set
 * `idempotency_key` at all when the model guards it against callers.
 *
 * @extends Factory<MessageCostEntry>
 */
final class MessageCostEntryFactory extends Factory
{
    protected $model = MessageCostEntry::class;

    public function definition(): array
    {
        return [
            'kind' => MessageCostKind::OutboundSms,
            'cost_millicents' => 7,
            'currency' => 'USD',
            'segments' => 1,
            'ref_type' => null,
            'ref_id' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
