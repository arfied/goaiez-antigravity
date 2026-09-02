<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class EdgeDeployAction
{
    public function handle(
        int $businessId,
        int $edgeZoneId,
        int $pageId = 0,
        string $commitId = '',
        string $businessName = '',
        int $measuredTtfbMs = 120,
        int $speedBudgetMs = 1500
    ): array {
        return DB::transaction(function () use ($businessId, $edgeZoneId, $pageId, $commitId, $businessName, $measuredTtfbMs, $speedBudgetMs) {
            $zone = EdgeZone::where('business_id', $businessId)->findOrFail($edgeZoneId);

            // 1. SSL Certificate check: a site cannot be published without a valid certificate (TEST ANCHOR)
            if (! $zone->has_valid_ssl) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'SSL_CERTIFICATE_REQUIRED',
                    'message' => 'A site cannot be published without a valid SSL certificate',
                ];
            }

            $deployHash = 'deploy_'.Str::random(16);

            $deployment = Deployment::create([
                'business_id' => $businessId,
                'edge_zone_id' => $zone->id,
                'deploy_hash' => $deployHash,
                'status' => 'deploying',
                'speed_index' => ($measuredTtfbMs <= $speedBudgetMs) ? 100 : 40,
                'speed_budget_ms' => $speedBudgetMs,
                'measured_ttfb_ms' => $measuredTtfbMs,
            ]);

            // 2. Speed budget check: failing speed budget rolls back automatically (TEST ANCHOR)
            if ($measuredTtfbMs > $speedBudgetMs) {
                $deployment->update([
                    'status' => 'rolled_back',
                    'rollback_reason' => "TTFB of {$measuredTtfbMs}ms exceeded budget of {$speedBudgetMs}ms",
                ]);

                Event::dispatch(new DeployRolledBack(
                    businessId: $businessId,
                    deploymentId: $deployment->id,
                    metric: 'ttfb_exceeded_budget',
                    reason: $deployment->rollback_reason
                ));

                return [
                    'status' => 'rolled_back',
                    'deployment_id' => $deployment->id,
                    'metric' => 'ttfb_exceeded_budget',
                    'measured_ttfb_ms' => $measuredTtfbMs,
                    'speed_budget_ms' => $speedBudgetMs,
                ];
            }

            $deployment->update([
                'status' => 'deployed',
                'deployed_at' => now(),
            ]);

            Event::dispatch(new DeployCompleted(
                businessId: $businessId,
                deploymentId: $deployment->id,
                domainName: $zone->domain_name,
                deployHash: $deployHash
            ));

            $features = [
                'pixel' => false,
                'chat' => true,
                'form_capture' => true,
                'dni' => true,
                'seo' => false, // Not provided by any module
                'schema' => false,
                'ssl' => $zone->has_valid_ssl,
            ];

            if ($zone->domain_name) {
                app(\App\Modules\X110\Actions\PixelInstallAction::class)->handle($businessId, $zone->domain_name);
                $features['pixel'] = true;
            }

            if ($pageId > 0 && $commitId !== '') {
                app(\App\Modules\X176\Actions\SchemaRenderAction::class)->handle($businessId, $pageId, $businessName, $commitId);
                $features['schema'] = true;
            }

            \Illuminate\Support\Facades\Storage::disk('local')->put("publish_{$deployHash}.json", json_encode([
                'deploy_id' => $deployHash,
                'features' => $features,
            ]));

            return [
                'status' => 'deployed',
                'deployment_id' => $deployment->id,
                'deploy_hash' => $deployHash,
                'measured_ttfb_ms' => $measuredTtfbMs,
                'features' => $features,
            ];
        });
    }
}
