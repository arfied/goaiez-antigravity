<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class EdgeDeployAction
{
    public function handle(
        int $businessId,
        int $edgeZoneId,
        int $measuredTtfbMs = 120,
        int $speedBudgetMs = 1500,
        ?int $pageId = null,
        ?string $commitId = null,
        ?string $businessName = null
    ): array {
        return DB::transaction(function () use ($businessId, $edgeZoneId, $measuredTtfbMs, $speedBudgetMs, $commitId, $pageId, $businessName) {
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

            // Compile HTML artifact to local storage
            $html = '<html><head>';
            $html .= "<meta name=\"ssl\" content=\"valid\">\n";
            $html .= "</head><body>\n";

            if ($commitId) {
                $version = PageVersion::where('commit_id', $commitId)->first();
                if ($version) {
                    if ($version->pixel_installed) {
                        $html .= "<script id=\"x110-pixel\" src=\"/pixel.js\"></script>\n";
                    }
                    if (is_array($version->content_blocks)) {
                        foreach ($version->content_blocks as $block) {
                            if (($block['type'] ?? '') === 'chat') {
                                $html .= "<div class=\"chat-widget-container\"></div>\n";
                            }
                            if (($block['type'] ?? '') === 'form_capture') {
                                $html .= "<form class=\"form-capture-x155\"></form>\n";
                            }
                            if (($block['type'] ?? '') === 'dni') {
                                $html .= "<div class=\"dni-pool-x137\"></div>\n";
                            }
                        }
                    }
                }
            }

            if ($pageId !== null && $businessName !== null && $commitId !== null) {
                $schemaResult = app(\App\Modules\X176\Actions\SchemaRenderAction::class)->handle(
                    $businessId,
                    $pageId,
                    $businessName,
                    $commitId
                );

                if (isset($schemaResult['json_ld'])) {
                    $html .= "<script type=\"application/ld+json\">\n" . json_encode($schemaResult['json_ld']) . "\n</script>\n";
                }
                
                // seo is completely missing from X-176, so we do not emit anything for it.
            }

            $html .= '</body></html>';

            Storage::disk('local')->put("sites/{$deployHash}.html", $html);

            return [
                'status' => 'deployed',
                'deployment_id' => $deployment->id,
                'deploy_hash' => $deployHash,
                'measured_ttfb_ms' => $measuredTtfbMs,
            ];
        });
    }
}
