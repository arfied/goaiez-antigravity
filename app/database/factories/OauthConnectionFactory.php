<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Enums\OauthProvider;
use App\Models\OauthConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context, and a factory that creates its own parent creates a second tenant
 * (see LocationFactory).
 *
 * Token values are obviously-fake hex, standing in for ciphertext. Never a
 * real credential, not even an expired one.
 *
 * @extends Factory<OauthConnection>
 */
final class OauthConnectionFactory extends Factory
{
    protected $model = OauthConnection::class;

    public function definition(): array
    {
        return [
            'provider' => OauthProvider::Google,
            'external_account_id' => fake()->unique()->numerify('accounts/##########'),
            'display_label' => fake()->company(),
            'access_token_enc' => fake()->sha256(),
            'refresh_token_enc' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/business.manage'],
            'status' => ConnectionStatus::Active,
            'last_refreshed_at' => now(),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'status' => ConnectionStatus::Expired,
            'token_expires_at' => now()->subDay(),
        ]);
    }
}
