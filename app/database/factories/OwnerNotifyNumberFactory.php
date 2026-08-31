<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\OwnerNotifyNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The owner channel's operational number (10540) — one row per business.
 *
 * Defaults business_id explicitly, unlike a BelongsToTenant model: this one
 * carries no tenancy trait, so nothing fills it from context.
 *
 * @extends Factory<OwnerNotifyNumber>
 */
final class OwnerNotifyNumberFactory extends Factory
{
    protected $model = OwnerNotifyNumber::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'e164' => '+15555550100',
            'stopped_at' => null,
        ];
    }

    public function stopped(): self
    {
        return $this->state(fn (): array => [
            'stopped_at' => now(),
        ]);
    }
}
