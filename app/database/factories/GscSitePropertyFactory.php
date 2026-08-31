<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GscPermissionLevel;
use App\Models\GscSiteProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default `business_id` — BelongsToTenant fills it from the tenant in
 * context, and a factory that creates its own parent creates a second tenant
 * (see LocationFactory).
 *
 * ⚠️ `location_id` is deliberately not defaulted either. The table is unique on
 * it, so a factory minting its own location would make two calls to `create()`
 * succeed where production would refuse the second — a factory that is easier to
 * use than the schema is one that hides the constraint the schema exists for.
 *
 * @extends Factory<GscSiteProperty>
 */
final class GscSitePropertyFactory extends Factory
{
    protected $model = GscSiteProperty::class;

    public function definition(): array
    {
        return [
            // A domain property, which is the shape most small businesses have.
            // `example.com` rather than a faker domain: it is IANA-reserved, so a
            // fixture can never name a site somebody actually owns.
            'site_url' => 'sc-domain:example.com',
            'permission_level' => GscPermissionLevel::Owner,
            'chosen_at' => now(),
        ];
    }

    /**
     * A URL-prefix property, trailing slash and all.
     */
    public function urlPrefix(): self
    {
        return $this->state(fn (): array => ['site_url' => 'https://example.com/']);
    }

    /**
     * The lesser role that can still read Performance data.
     *
     * Exists because the instinct is that it cannot, and Google's own permissions
     * page says otherwise — a fixture is the cheapest place to keep that fact
     * where somebody will meet it.
     */
    public function restricted(): self
    {
        return $this->state(fn (): array => ['permission_level' => GscPermissionLevel::RestrictedUser]);
    }
}
