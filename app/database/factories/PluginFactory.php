<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PluginType;
use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory). embed_key is a random UUID because it is
 * globally unique and public — never derived from tenant data.
 *
 * @extends Factory<Plugin>
 */
final class PluginFactory extends Factory
{
    protected $model = Plugin::class;

    public function definition(): array
    {
        return [
            'type' => PluginType::ReviewWidget,
            'embed_key' => (string) Str::uuid(),
            'config' => null,
            'min_stars_to_show' => 1,
            'allowed_domains' => [fake()->domainName()],
            'version' => '1',
        ];
    }
}
