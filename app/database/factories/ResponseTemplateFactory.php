<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BrandVoice;
use App\Models\ResponseTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<ResponseTemplate>
 */
final class ResponseTemplateFactory extends Factory
{
    protected $model = ResponseTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'body' => fake()->paragraph(),
            'brand_voice' => BrandVoice::FriendlyWarm,
            'is_active' => true,
        ];
    }
}
