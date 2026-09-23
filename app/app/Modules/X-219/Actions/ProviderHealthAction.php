<?php

declare(strict_types=1);

namespace App\Modules\X219\Actions;

use App\Modules\X219\Events\ProviderDegraded;
use App\Modules\X219\Models\AiProvider;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\Event;

final class ProviderHealthAction
{
    public const DEGRADED_ERROR_RATE_PCT = 50;

    private function degradedErrorRatePct(): int
    {
        return $this->registry->int('ai.provider.degraded_error_rate_pct');
    }

    public function __construct(private DefaultsRegistry $registry) {}

    public function updateHealth(int $businessId, int $providerId, int $latencyP95Ms, int $errorRatePct): AiProvider
    {
        $provider = AiProvider::where('business_id', $businessId)->findOrFail($providerId);

        $status = ($errorRatePct > 5 || $latencyP95Ms > 3000) ? 'degraded' : 'healthy';
        if ($errorRatePct >= $this->degradedErrorRatePct()) {
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
