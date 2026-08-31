<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OauthProvider;
use App\Models\ProviderHealth;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<ProviderHealth>
 */
final class ProviderHealthFactory extends Factory
{
    protected $model = ProviderHealth::class;

    public function definition(): array
    {
        return [
            'provider' => OauthProvider::Google,
            'last_sync_at' => now()->subMinutes(5),
            'token_status' => 'active',
        ];
    }
}
