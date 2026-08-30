<?php

declare(strict_types=1);

namespace App\Modules\X219\Actions;

use App\Modules\X219\Events\ProviderDegraded;
use App\Modules\X219\Models\AiProvider;
use Illuminate\Support\Facades\Event;

final class ProviderHealthAction
{
    public function updateHealth(int $businessId, int $providerId, int $latencyP95Ms, int $errorRatePct): AiProvider
    {
        $provider = AiProvider::where('business_id', $businessId)->findOrFail($providerId);

        $status = ($errorRatePct > 5 || $latencyP95Ms > 3000) ? 'degraded' : 'healthy';
        if ($errorRatePct >= 50) {
            $status = 'down';
        }

        $provider->update([
            'latency_p95_ms' => $latencyP95Ms,
            'error_rate_pct' => $errorRatePct,
            'status' => $status,
        ]);

        if ($status !== 'healthy') {
            Event::dispatch(new ProviderDegraded(
                businessId: $businessId,
                providerName: $provider->provider_name,
                errorRatePct: $errorRatePct
            ));
        }

        return $provider;
    }
}
