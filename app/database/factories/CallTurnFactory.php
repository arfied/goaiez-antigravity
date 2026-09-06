<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\X66\Models\CallTurn;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallTurnFactory extends Factory
{
    protected $model = CallTurn::class;

    public function definition(): array
    {
        return [
            'speaker' => 'caller',
            'transcript' => $this->faker->sentence(),
        ];
    }
}
