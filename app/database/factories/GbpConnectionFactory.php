<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GbpConnectionStatus;
use App\Enums\GbpProvider;
use App\Models\GbpConnection;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⚠️ **The default state is `pending`, not `connected`.** A factory whose
 * default is the finished state makes every test that forgets to say so pass
 * against a connection nobody completed — and `pending` is the state the flow
 * actually creates first. Ask for `->connected()` when the test is about a
 * location that can be read from.
 *
 * @extends Factory<GbpConnection>
 */
final class GbpConnectionFactory extends Factory
{
    protected $model = GbpConnection::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'provider' => GbpProvider::Zernio,
            'provider_profile_ref' => fake()->regexify('[0-9a-f]{24}'),
            'account_ref' => null,
            'external_label' => null,
            'status' => GbpConnectionStatus::Pending,
        ];
    }

    public function connected(): self
    {
        return $this->state(fn (): array => [
            'account_ref' => fake()->regexify('[0-9a-f]{24}'),
            'external_label' => fake()->company(),
            'status' => GbpConnectionStatus::Connected,
            'connected_at' => now(),
        ]);
    }

    public function disconnected(): self
    {
        return $this->state(fn (): array => [
            'account_ref' => fake()->regexify('[0-9a-f]{24}'),
            'external_label' => fake()->company(),
            'status' => GbpConnectionStatus::Disconnected,
            'connected_at' => now()->subMonth(),
            'disconnected_at' => now(),
        ]);
    }
}
