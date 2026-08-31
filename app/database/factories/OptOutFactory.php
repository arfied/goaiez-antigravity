<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OptOutScope;
use App\Enums\OutreachChannel;
use App\Models\OptOut;
use App\Support\Identifier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptOut>
 */
final class OptOutFactory extends Factory
{
    protected $model = OptOut::class;

    public function definition(): array
    {
        return [
            'scope' => OptOutScope::Platform,
            'business_id' => null,
            'identifier_type' => OutreachChannel::Sms,
            'value_hash' => Identifier::hash('+15551234567', OutreachChannel::Sms),
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
