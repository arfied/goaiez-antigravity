<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Modules\X111\Models\TenantTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantTicketFactory extends Factory
{
    protected $model = TenantTicket::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'source' => 'human_requested',
            'category' => 'general',
            'full_transcript' => 'Test transcript',
            'sla_due_at' => now()->addHour(),
            'status' => 'open',
        ];
    }
}
