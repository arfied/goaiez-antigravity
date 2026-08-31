<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShortLinkPurpose;
use App\Models\ShortLink;
use App\Services\ShortLinks\ShortLinks;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<ShortLink>
 */
final class ShortLinkFactory extends Factory
{
    protected $model = ShortLink::class;

    public function definition(): array
    {
        return [
            // Built the same way the minter builds one, so a factory-made link
            // is matchable by the route's own token constraint. A different
            // shape here would let a test pass against a token the redirector
            // could never route.
            'token' => Str::random(ShortLinks::TOKEN_LENGTH),
            'target_url' => 'https://example.test/'.fake()->slug(),
            'purpose' => ShortLinkPurpose::ReviewInvite,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): self
    {
        return $this->state(fn (): array => ['revoked_at' => now()->subHour()]);
    }
}
