<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LiftSource;
use App\Enums\OptOutScope;
use App\Enums\OutreachChannel;
use App\Models\SuppressionLift;
use App\Support\Identifier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuppressionLift>
 */
final class SuppressionLiftFactory extends Factory
{
    protected $model = SuppressionLift::class;

    public function definition(): array
    {
        return [
            'scope' => OptOutScope::Platform,
            'business_id' => null,
            'identifier_type' => OutreachChannel::Sms,
            'value_hash' => Identifier::hash('+15551234567', OutreachChannel::Sms),
            'generation' => 0,
            'source' => LiftSource::CarrierStart,
            'actor' => 'carrier',
            'created_at' => now(),
        ];
    }

    public function forIdentifier(string $identifier, OutreachChannel $channel): self
    {
        return $this->state(fn (): array => [
            'identifier_type' => $channel,
            'value_hash' => Identifier::hash($identifier, $channel),
        ]);
    }

    public function forTenant(int $businessId): self
    {
        return $this->state(fn (): array => [
            'scope' => OptOutScope::Tenant,
            'business_id' => $businessId,
        ]);
    }
}
