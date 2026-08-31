<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DataClassification;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
final class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /**
     * Establish the tenant between making the model and storing it.
     *
     * afterMaking runs after the attributes are assembled and before the insert,
     * which is the only window where this works: the row-level security policy
     * compares `id` against the session tenant, so it must already be this
     * business by the time the INSERT runs — including its RETURNING clause.
     *
     * Leaves the tenant established, matching Business::provision(). Tests that
     * want a different tenant afterwards say so with Tenancy::actingAs().
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Business $business): void {
            Tenancy::set((int) $business->id);
        });
    }

    /**
     * forceFill because `id` is set from the pre-allocated sequence value rather
     * than by the insert — see Business::provision() for why that inversion is
     * necessary at all.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Business
    {
        return (new Business)->forceFill($attributes);
    }

    public function definition(): array
    {
        return [
            // Reserved before the insert, not assigned by it.
            'id' => Business::allocateId(),
            'owner_user_id' => User::factory(),
            'name' => fake()->company(),
            'legal_name' => fake()->company().' LLC',
            'entity_type' => 'llc',
            'address' => [
                'line1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'region' => fake()->randomElement(['CA', 'TX', 'NY', 'FL', 'IL', 'WA', 'GA', 'OH']),
                'postal_code' => fake()->postcode(),
                'country' => 'US',
            ],
            'currency' => 'USD',
            'vertical' => fake()->randomElement(['hvac', 'dental', 'salon', 'legal', 'auto']),
            'data_classification' => DataClassification::Pii,

            // ⚠️ `pixel_tenant_id` WAS HERE AND THE COLUMN IS GONE. It was the
            // pixel's public key, this factory was its only writer anywhere, and
            // nothing in `app/` ever set it — so a collector built against it
            // would have resolved nothing for every real tenant while every
            // factory-built test passed. The key is now `pixel_keys`, minted by
            // `PixelKeys::ensureFor()` at provisioning; see that table's
            // migration for why it could not stay a column here.
            // ⚠️ `messaging_mode` WAS HERE AND THE COLUMN IS GONE, AND IT WENT
            // FOR A REASON THIS LINE HELPED HIDE. It was one half of `26`
            // §1.2's unbuilt `SwapNumberJob` — the other half was
            // `dedicated_number_id` on the adjacent line — and nothing ever
            // wrote either. This factory seeding the default is what made the
            // dead one look alive: every fixture had a plausible value, so any
            // screen rendering it would have shown one. That is
            // `locations.current_rating`'s disguise, and it is why the sibling
            // with no factory line was the one somebody finally noticed.
            // Tenant-dedicated numbering lives on `phone_numbers.business_id`;
            // see the 2026-08-23 drop migration (8390-8409).
            'marketing_sends_enabled' => false,
        ];
    }

    /**
     * A business whose data must route through the PHI path.
     */
    public function phi(): self
    {
        return $this->state(fn (): array => [
            'data_classification' => DataClassification::Phi,
            'vertical' => 'medical',
        ]);
    }
}
