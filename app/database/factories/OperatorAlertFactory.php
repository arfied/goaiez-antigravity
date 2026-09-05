<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Modules\X111\Models\OperatorAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperatorAlertFactory extends Factory
{
    protected $model = OperatorAlert::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'severity' => 'high',
            'action_verb_message' => 'Investigate issue',
            'status' => 'open',
        ];
    }
}
