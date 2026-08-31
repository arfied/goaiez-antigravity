<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PixelDeliverySample;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PixelDeliverySample>
 */
final class PixelDeliverySampleFactory extends Factory
{
    protected $model = PixelDeliverySample::class;

    public function definition(): array
    {
        return [
            'build_token' => bin2hex(random_bytes(16)),
            'bucket' => Carbon::now()->startOfMinute(),
            'pageviews' => 0,
            'js_errors' => 0,
        ];
    }
}
