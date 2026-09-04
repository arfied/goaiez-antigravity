<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\CAi\Models\AiCall;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiCallFactory extends Factory
{
    protected $model = AiCall::class;

    public function definition(): array
    {
        return [
            'business_id' => 1,
            'task' => 'test-task',
            'provider' => 'openai',
            'model' => 'gpt-4',
            'model_served' => 'gpt-4',
            'input_tokens' => 10,
            'output_tokens' => 10,
            'tokens_in' => 10,
            'tokens_out' => 10,
            'cost_hundredths_cents' => 1000,
            'cost_cents' => 10,
            'refused' => false,
            'failure_reason' => null,
            'ttft_ms' => 150,
            'latency_ms' => 300,
            'usage_unavailable' => false,
        ];
    }
}
