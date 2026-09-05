<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X103\Models\PageVersion;
use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Modules\X176\Actions\SchemaRenderAction;
use App\Modules\X176\Actions\SeoRenderAction;
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

            // Compile HTML artifact to local storage
            $html = '<html><head>';
            $html .= "<meta name=\"ssl\" content=\"valid\">\n";
            $html .= "</head><body>\n";

            if ($commitId) {
                $version = PageVersion::where('commit_id', $commitId)->first();
                if ($version) {
                    if ($version->pixel_installed) {
                        $pixelSrc = route('pixel.bundle.pointer', absolute: false);
                        $html .= "<script id=\"x110-pixel\" src=\"{$pixelSrc}\"></script>\n";
                    }
                    $blockTypes = is_array($version->content_blocks)
                        ? array_column($version->content_blocks, 'type')
                        : [];

                    $hasChat = $version->chat_installed || in_array('chat', $blockTypes, true);
                    $hasForm = $version->form_capture_installed || in_array('form_capture', $blockTypes, true);
                    $hasDni = $version->dni_installed || in_array('dni', $blockTypes, true);

                    if ($hasChat) {
                        $html .= "<div class=\"chat-widget-container\"></div>\n";
                    }
                    if ($hasForm) {
                        $html .= "<form class=\"form-capture-x155\"></form>\n";
                    }
                    if ($hasDni) {
                        $html .= "<div class=\"dni-pool-x137\"></div>\n";
                    }
                }
            }

            if ($pageId !== null && $businessName !== null && $commitId !== null) {
                $seoResult = app(SeoRenderAction::class)->handle(
                    $businessId,
                    $pageId,
                    $businessName,
                    $commitId,
                    $zone->domain_name
                );

                $escapedTitle = e($seoResult['title']);
                $escapedDesc = e($seoResult['description']);
                $escapedCanonical = e($seoResult['canonical']);

                $html = str_replace(
                    '</head>',
                    "<title id=\"seo-meta-x176\">{$escapedTitle}</title>\n".
                    "<meta name=\"description\" content=\"{$escapedDesc}\">\n".
                    "<link rel=\"canonical\" href=\"{$escapedCanonical}\">\n</head>",
                    $html
                );

                $schemaResult = app(SchemaRenderAction::class)->handle(
                    $businessId,
                    $pageId,
                    $businessName,
                    $commitId,
                    $zone->domain_name
                );

                if (isset($schemaResult['json_ld'])) {
                    $html .= "<script type=\"application/ld+json\">\n".json_encode($schemaResult['json_ld'], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)."\n</script>\n";
                }
            }

            $html .= '</body></html>';

            Storage::disk('local')->put("sites/{$deployHash}.html", $html);

            // Nothing outside this action learns of a deploy until the artifact it
            // announces is on disk (R245, 2026-09-05): the supersede, the status flip and
            // DeployCompleted all follow the write, because ModuleServiceProvider's route
            // serves a `deployed` row by reading that exact file.
            Deployment::where('business_id', $businessId)
                ->where('edge_zone_id', $zone->id)
                ->where('status', 'deployed')
                ->where('id', '!=', $deployment->id)
                ->update(['status' => 'superseded']);

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


            return [
                'status' => 'deployed',
                'deployment_id' => $deployment->id,
                'deploy_hash' => $deployHash,
                'measured_ttfb_ms' => $measuredTtfbMs,
            ];
        });
    }
}
