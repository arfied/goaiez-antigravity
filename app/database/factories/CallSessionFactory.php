<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\X66\Models\CallSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallSessionFactory extends Factory
{
    protected $model = CallSession::class;

    public function definition(): array
    {
        return [
            'call_sid' => 'sid-'.$this->faker->unique()->numerify('######'),
            'from_phone' => '+1555010'.$this->faker->unique()->numerify('####'),
            'to_phone' => '+15550100000',
            'status' => 'completed',
            'latency_ms' => 150,
        ];
    }
}
