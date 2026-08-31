<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerMerge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default `business_id` — `BelongsToTenant` fills it from the tenant in
 * context, the same as every other factory here.
 *
 * ⚠️ **THIS FACTORY EXISTS FOR THE ISOLATION TEST AND NOT FOR THE FEATURE
 * TESTS.** A merge row made here has not moved anything: the survivor still
 * holds its own values, the merged-away row still holds its identifiers, and
 * `customers.merged_into_id` is untouched. Proving a row cannot cross a tenant
 * boundary needs no consistent state, which is exactly why an isolation test
 * passes perfectly against a table nothing writes (399's tell). Anything
 * asserting merge *behaviour* drives `CustomerMerges` instead.
 *
 * @extends Factory<CustomerMerge>
 */
final class CustomerMergeFactory extends Factory
{
    protected $model = CustomerMerge::class;

    public function definition(): array
    {
        return [
            'survivor_id' => Customer::factory(),
            'merged_id' => Customer::factory(),
            'changes' => [
                'survivor_before' => ['name' => null, 'tags' => null, 'email' => null, 'phone' => null],
                'merged_before' => ['email' => null, 'phone' => null],
                'chose' => ['name' => 'survivor', 'tags' => 'survivor'],
            ],
            'merged_at' => now(),
        ];
    }
}
