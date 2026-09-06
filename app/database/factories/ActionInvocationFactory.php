<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Modules\X122\Models\ActionInvocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActionInvocationFactory extends Factory
{
    protected $model = ActionInvocation::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'action_name' => 'test.action',
            'parameters' => [],
            'result' => [],
            'actor_type' => 'assistant',
            'status' => 'completed',
            'geo_city' => 'Austin',
            'geo_country' => 'US',
            'ip_address' => '127.0.0.1',
        ];
    }
}
