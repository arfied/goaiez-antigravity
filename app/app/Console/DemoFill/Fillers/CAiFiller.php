<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\CAi\Models\AiCall;

class CAiFiller implements DemoFiller
{
    public function module(): string
    {
        return 'C-Ai';
    }

    public function fill(Business $business): int
    {
        $count = AiCall::where('business_id', $business->id)
            ->where('task', 'like', self::MARKER.'%')
            ->count();

        if ($count > 0) {
            return 0;
        }

        $rows = 0;

        AiCall::create([
            'business_id' => $business->id,
            'task' => self::MARKER.'summary',
            'task_id' => null,
            'provider' => 'demo·simulated',
            'model' => 'default_primary',
            'model_requested' => 'default_primary',
            'model_served' => 'default_primary',
            'ttft_ms' => 200,
            'latency_ms' => 300,
            'tokens_in' => 150,
            'tokens_out' => 50,
            'cost_cents' => 10,
            'prompt_version' => 1,
            'usage_unavailable' => false,
        ]);
        $rows++;

        AiCall::create([
            'business_id' => $business->id,
            'task' => self::MARKER.'reply',
            'task_id' => null,
            'provider' => 'demo·simulated',
            'model' => 'default_primary',
            'model_requested' => 'default_primary',
            'model_served' => 'default_primary',
            'ttft_ms' => 350,
            'latency_ms' => 450,
            'tokens_in' => 150,
            'tokens_out' => 50,
            'cost_cents' => 10,
            'prompt_version' => 1,
            'usage_unavailable' => false,
        ]);
        $rows++;

        return $rows;
    }

    public function purge(Business $business): int
    {
        $count = AiCall::where('business_id', $business->id)
            ->where('task', 'like', self::MARKER.'%')
            ->delete();

        return $count;
    }
}
