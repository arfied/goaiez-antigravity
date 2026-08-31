<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantLinkKind;
use App\Models\TenantLinkRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⚠️ **NO FEE IN THE DEFAULT STATE, AND THAT IS THE HONEST DEFAULT.** R13 makes a
 * payment link with no fee the ordinary case — most businesses do not charge to
 * come out — and a factory that always set one would hide the branch skill 6
 * actually turns on. `withFee()` is how a test asks for the other case, which is
 * decision 1597's rule: a factory default is a production null nobody sees.
 *
 * @extends Factory<TenantLinkRecord>
 */
final class TenantLinkRecordFactory extends Factory
{
    protected $model = TenantLinkRecord::class;

    public function definition(): array
    {
        return [
            'kind' => TenantLinkKind::Booking,
            'label' => 'Book a visit',
            'destination' => 'https://booking.example.test/'.fake()->slug(),
            'slug' => null,
            'fee_cents' => null,
            'fee_currency' => null,
            'fee_covers' => null,
        ];
    }

    public function payment(): self
    {
        return $this->state(fn (): array => [
            'kind' => TenantLinkKind::Payment,
            'label' => 'Pay your call-out fee',
            'destination' => 'https://pay.example.test/'.fake()->slug(),
        ]);
    }

    public function withFee(int $cents, ?string $covers = null): self
    {
        return $this->state(fn (): array => [
            'fee_cents' => $cents,
            'fee_currency' => 'USD',
            'fee_covers' => $covers,
        ]);
    }

    public function document(string $label = 'Price sheet'): self
    {
        return $this->state(fn (): array => [
            'kind' => TenantLinkKind::Document,
            'label' => $label,
            'destination' => 'https://docs.example.test/'.fake()->slug(),
            'slug' => Str::slug($label),
        ]);
    }
}
