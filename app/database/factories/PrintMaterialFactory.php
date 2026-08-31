<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\PrintMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<PrintMaterial>
 */
final class PrintMaterialFactory extends Factory
{
    protected $model = PrintMaterial::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'type' => 'qr_poster',
            'call_to_action' => 'How did we do? Scan to tell us.',
        ];
    }
}
