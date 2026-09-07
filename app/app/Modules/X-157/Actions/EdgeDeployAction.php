<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Models\Business;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\PageVersion;
use App\Modules\X108\Models\Appointment;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Modules\X176\Actions\InternalLinkRenderAction;
use App\Modules\X176\Actions\LlmsTxtRenderAction;
use App\Modules\X176\Actions\SchemaRenderAction;
use App\Modules\X176\Actions\SeoRenderAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
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

            if ($commitId) {
                // X-103 ↔ X-157 seam (R245): derive ssl_installed from EdgeZone.has_valid_ssl
                PageVersion::where('commit_id', $commitId)->update(['ssl_installed' => $zone->has_valid_ssl]);
            }

            // 1. SSL Certificate check: a site cannot be published without a valid certificate (TEST ANCHOR)
            if (! $zone->has_valid_ssl) {
                return [
                    'status' => 'refused',
                    'refusal_code' => 'SSL_CERTIFICATE_REQUIRED',
                    'message' => 'A site cannot be published without a valid SSL certificate',
                ];
            }

            // A deploy is a page deploy (pageId, commitId and businessName all given) or a
            // zone deploy (none of them). A partial set means a caller lost one of the three
            // on the way — ModuleServiceProvider:70 sources businessName from
            // Business::…->value('name'), which is null when that row is not visible — and it
            // would publish four of the seven required elements under a `deployed` row
            // (R245, 2026-09-05). Refuse it: the transaction rolls the row back and the
            // listener's catch keeps the page published.
            $pageArgs = array_filter(
                [$pageId, $commitId, $businessName],
                static fn ($arg): bool => $arg !== null
            );

            if ($pageArgs !== [] && count($pageArgs) !== 3) {
                throw new \RuntimeException('a page deploy needs pageId, commitId and businessName together; got '.count($pageArgs).' of 3');
            }

            $deployHash = 'deploy_'.Str::random(16);

            $deployment = Deployment::create([
                'business_id' => $businessId,
                'edge_zone_id' => $zone->id,
                'page_id' => $pageId,
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
            $videos = [];
            $events = [];

            $business = Business::find($businessId);
            $address = is_array($business?->address) ? $business->address : null;

            $appointments = Appointment::where('business_id', $businessId)
                ->where('start_time', '>=', now())
                ->get();
            foreach ($appointments as $apt) {
                $events[] = [
                    'name' => (string) ($apt->service_name ?? 'Appointment'),
                    'startDate' => $apt->start_time?->toIso8601String(),
                    'endDate' => $apt->end_time?->toIso8601String(),
                ];
            }

            $html = '<html><head>';
            $html .= "<meta name=\"ssl\" content=\"valid\">\n";
            $html .= "</head><body>\n";

            if ($commitId) {
                $version = PageVersion::where('commit_id', $commitId)->first();
                if ($version) {
                    $blockTypes = is_array($version->content_blocks)
                        ? array_column($version->content_blocks, 'type')
                        : [];

                    if (in_array('pixel_script', $blockTypes, true)) {
                        $pixelSrc = route('pixel.bundle.pointer', absolute: false);
                        $html .= "<script id=\"x110-pixel\" src=\"{$pixelSrc}\"></script>\n";
                    }

                    $hasChat = in_array('chat_widget', $blockTypes, true);
                    $hasForm = in_array('form_capture', $blockTypes, true);
                    $hasDni = in_array('dni_script', $blockTypes, true);

                    foreach ($version->content_blocks as $block) {
                        if (($block['type'] ?? '') === 'video_embed') {
                            // VideoObject injected on publish (TEST ANCHOR, G16-25, ruling 41)
                            $videos[] = [
                                'name' => $block['name'] ?? null,
                                'contentUrl' => $block['contentUrl'] ?? null,
                                'uploadDate' => $block['uploadDate'] ?? null,
                            ];
                        }
                    }

                    if ($hasChat) {
                        $html .= "<div class=\"chat-widget-container\"></div>\n";
                    }
                    if ($hasForm) {
                        $formId = FormDefinition::where('business_id', $businessId)->orderBy('id')->value('id');
                        $action = $formId === null
                            ? ''
                            : " method=\"post\" action=\"/sites/{$businessId}/{$deployHash}/forms/{$formId}\"";
                        $html .= "<form class=\"form-capture-x155\"{$action}></form>\n";
                    }
                    if ($hasDni) {
                        $html .= "<div class=\"dni-pool-x137\"></div>\n";
                    }
                }
            }

            if ($pageId !== null && $businessName !== null && $commitId !== null) {
                $breadcrumbs = [];
                $page = Page::find($pageId);
                if ($page && ! empty($page->slug) && ! empty($page->title)) {
                    $parts = explode('/', trim($page->slug, '/'));
                    if (count($parts) > 1) {
                        $paths = [];
                        $current = '';
                        foreach ($parts as $part) {
                            $current = $current ? $current.'/'.$part : $part;
                            $paths[] = $current;
                        }

                        $hierarchyPages = Page::where('business_id', $businessId)
                            ->whereIn('slug', $paths)
                            ->get()
                            ->keyBy('slug');

                        $usable = true;
                        foreach ($paths as $path) {
                            if (! isset($hierarchyPages[$path]) || empty($hierarchyPages[$path]->title)) {
                                $usable = false;
                                break;
                            }
                            $breadcrumbs[] = [
                                'name' => $hierarchyPages[$path]->title,
                                'slug' => $path,
                            ];
                        }
                        if (! $usable) {
                            $breadcrumbs = [];
                        }
                    }
                }

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
                    $zone->domain_name,
                    videos: $videos ?: null,
                    events: $events ?: null,
                    address: $address ?: null,
                    breadcrumbs: $breadcrumbs ?: null
                );

                if (isset($schemaResult['json_ld'])) {
                    $html .= "<script type=\"application/ld+json\">\n".json_encode($schemaResult['json_ld'], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)."\n</script>\n";
                }

                $page = Page::find($pageId);
                if ($page) {
                    $contentBlocks = (isset($version) && $version && is_array($version->content_blocks)) ? $version->content_blocks : [];
                    $llmsTxtContent = app(LlmsTxtRenderAction::class)->handle(
                        $businessName,
                        $page->title,
                        $page->slug,
                        $contentBlocks
                    );
                    if (Storage::disk('local')->put("sites/{$deployHash}.llms.txt", $llmsTxtContent) === false) {
                        Log::warning("the llms.txt artifact could not be written: sites/{$deployHash}.llms.txt");
                    }
                }
            }

            $internalLinksHtml = app(InternalLinkRenderAction::class)->handle($businessId);
            if ($internalLinksHtml !== '') {
                $html .= $internalLinksHtml;
            }

            $html .= '</body></html>';

            // The local disk is configured 'throw' => false (config/filesystems.php:37), so a
            // failed write returns false rather than raising (R245, 2026-09-05). Refuse the
            // deploy: the transaction rolls the row back and ModuleServiceProvider's catch
            // keeps the page published, rather than announcing an artifact that is not there.
            if (Storage::disk('local')->put("sites/{$deployHash}.html", $html) === false) {
                throw new \RuntimeException("the site artifact could not be written: sites/{$deployHash}.html");
            }

            // Nothing outside this action learns of a deploy until the artifact it
            // announces is on disk (R245, 2026-09-05): the supersede, the status flip and
            // DeployCompleted all follow the write, because ModuleServiceProvider's route
            // serves a `deployed` row by reading that exact file. A deploy supersedes only
            // the previous deploy of the same page (R245, 2026-09-05).
            Deployment::where('business_id', $businessId)
                ->where('edge_zone_id', $zone->id)
                ->where('page_id', $pageId)
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
