<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Models\Business;
use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X103\Actions\PageVersionAction;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X108\Actions\AppointmentListAction;
use App\Modules\X155\Actions\FormReadAction;
use App\Modules\X157\Events\DeployCompleted;
use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\CustomDomainRequest;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Modules\X163\Actions\QuotablePriceAction;
use App\Modules\X176\Actions\InternalLinkRenderAction;
use App\Modules\X176\Actions\LlmsTxtRenderAction;
use App\Modules\X176\Actions\SchemaRenderAction;
use App\Modules\X176\Actions\SeoRenderAction;
use App\Services\Config\DefaultsRegistry;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Pixel\PixelKeys;
use App\Support\Money;
use App\Support\PlanPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

final class EdgeDeployAction
{
    public const SPEED_BUDGET_MS = 1500;

    public const PRICEBOOK_ITEMS_MAX = 20;

    private const DNI_SCRIPT = <<<'JS'
(function(){var c=document.querySelector('div[data-dni-url]');if(!c){return;}var u=c.getAttribute('data-dni-url');if(!u){return;}var t='';try{var s=window.localStorage.getItem('_q_s');if(s){t=String(JSON.parse(s).id||'');}}catch(e){}if(!t){t='dni_'+Math.random().toString(36).slice(2)+Date.now().toString(36);}fetch(u+'?visitor_session_token='+encodeURIComponent(t),{credentials:'omit',headers:{Accept:'application/json'}}).then(function(r){return r.ok?r.json():null;}).then(function(d){if(!d||!d.number){return;}var a=document.createElement('a');a.setAttribute('href','tel:'+String(d.number).replace(/[^+0-9]/g,''));a.appendChild(document.createTextNode(String(d.number)));while(c.firstChild){c.removeChild(c.firstChild);}c.appendChild(a);}).catch(function(){});})();
JS;

    public function __construct(private DefaultsRegistry $defaults) {}

    public function handle(
        int $businessId,
        int $edgeZoneId,
        int $measuredTtfbMs = 120,
        ?int $speedBudgetMs = null,
        ?int $pageId = null,
        ?string $commitId = null,
        ?string $businessName = null,
        ?int $pageVariantId = null
    ): array {
        $speedBudgetMs ??= $this->defaults->int('sites.deploy.speed_budget_ms');

        return DB::transaction(function () use ($businessId, $edgeZoneId, $measuredTtfbMs, $speedBudgetMs, $commitId, $pageId, $businessName, $pageVariantId) {
            $zone = EdgeZone::where('business_id', $businessId)->findOrFail($edgeZoneId);

            if ($commitId) {
                // X-103 ↔ X-157 seam (R245): derive ssl_installed from EdgeZone.has_valid_ssl
                app(PageVersionAction::class)->recordSslInstalled($commitId, $zone->has_valid_ssl);
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
                'page_variant_id' => $pageVariantId,
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
            $faqs = [];

            $business = Business::find($businessId);
            $address = is_array($business?->address) ? $business->address : null;

            $appointments = app(AppointmentListAction::class)->forBusiness($businessId);
            foreach ($appointments as $apt) {
                if (trim((string) $apt['service_name']) === '' || $apt['start_time'] === null || $apt['end_time'] === null) {
                    continue;
                }
                $events[] = [
                    'name' => $apt['service_name'],
                    'startDate' => $apt['start_time']->toIso8601String(),
                    'endDate' => $apt['end_time']->toIso8601String(),
                ];
            }

            $stored = $this->defaults->value('billing.currency');
            $currency = is_string($stored) && trim($stored) !== '' ? strtoupper(trim($stored)) : 'USD';

            $productOffers = [];
            $priceBookItems = app(QuotablePriceAction::class)->options($businessId);
            // The old code did ->limit(20), so we do array_slice
            usort($priceBookItems, fn ($a, $b) => $a['id'] <=> $b['id']);
            $priceBookItems = array_slice($priceBookItems, 0, $this->defaults->int('sites.deploy.pricebook_items_max'));
            foreach ($priceBookItems as $item) {
                if (trim((string) $item['service_name']) === '') {
                    continue;
                }
                $productOffers[] = [
                    'name' => $item['service_name'],
                    'price' => $item['price_cents'] / 100,
                    'price_max' => isset($item['price_max_cents']) ? $item['price_max_cents'] / 100 : null,
                    'currency' => $currency,
                    'price_text' => PlanPricing::format(Money::of((int) $item['price_cents'], $currency)),
                    'price_max_text' => isset($item['price_max_cents']) ? PlanPricing::format(Money::of((int) $item['price_max_cents'], $currency)) : null,
                ];
            }

            $html = '<html><head>';
            $html .= "<meta name=\"ssl\" content=\"valid\">\n";

            $x176Usable = false;
            $breadcrumbs = [];
            if ($pageId !== null && $businessName !== null && $commitId !== null) {
                $x176Usable = true;
                $page = app(PageReadAction::class)->findForBusiness($businessId, $pageId);
                if ($page && ! empty($page->slug) && trim((string) $page->title) !== '') {
                    $parts = explode('/', trim($page->slug, '/'));
                    $paths = [];
                    $current = '';
                    foreach ($parts as $part) {
                        $current = $current ? $current.'/'.$part : $part;
                        $paths[] = $current;
                    }
                    $pages = app(PageReadAction::class)->publishedForSlugs($businessId, $paths);
                    $hierarchyPages = [];
                    foreach ($pages as $p) {
                        $norm = trim((string) $p->slug, '/');
                        if (isset($hierarchyPages[$norm])) {
                            $x176Usable = false;
                            break;
                        }
                        $hierarchyPages[$norm] = $p;
                    }
                    if ($x176Usable) {
                        foreach ($paths as $path) {
                            if (! isset($hierarchyPages[$path]) || trim((string) $hierarchyPages[$path]->title) === '') {
                                $x176Usable = false;
                                break;
                            }
                            if (count($parts) > 1) {
                                $breadcrumbs[] = [
                                    'name' => $hierarchyPages[$path]->title,
                                    'slug' => $path,
                                ];
                            }
                        }
                    }
                    if (! $x176Usable) {
                        $breadcrumbs = [];
                    }
                }
            }

            $headPage = null;
            if ($pageId !== null) {
                $headPage = app(PageReadAction::class)->findForBusiness($businessId, $pageId);
            }
            if (! $x176Usable) {
                $seoTitle = $headPage ? ($headPage->seo_title ?: $headPage->title ?: $businessName) : $businessName;
                $html .= '<title>'.e((string) $seoTitle)."</title>\n";
                if ($headPage && ! empty($headPage->seo_description)) {
                    $html .= '<meta name="description" content="'.e($headPage->seo_description)."\">\n";
                }
            }

            $html .= "</head><body>\n";

            if ($commitId) {
                $version = app(PageVersionAction::class)->forCommit($commitId);
                if ($version) {
                    $blockTypes = is_array($version->content_blocks)
                        ? array_column($version->content_blocks, 'type')
                        : [];

                    $business = Business::find($businessId);
                    $pixelKey = $business === null ? '' : app(PixelKeys::class)->ensureFor($business);
                    if (in_array('pixel_script', $blockTypes, true)) {
                        $pixelSrc = route('pixel.bundle.pointer', absolute: false);
                        $html .= "<script id=\"x110-pixel\" src=\"{$pixelSrc}\" data-k=\"".e($pixelKey)."\"></script>\n";
                    }

                    $hasChat = in_array('chat_widget', $blockTypes, true);
                    $hasForm = in_array('form_capture', $blockTypes, true);
                    $hasDni = in_array('dni_script', $blockTypes, true);

                    foreach ($version->content_blocks as $block) {
                        if (($block['type'] ?? '') === 'video_embed') {
                            if (! is_scalar($block['name'] ?? '') || ! is_scalar($block['contentUrl'] ?? '') || ! is_scalar($block['uploadDate'] ?? '')
                                || trim((string) ($block['name'] ?? '')) === '' || trim((string) ($block['contentUrl'] ?? '')) === '' || trim((string) ($block['uploadDate'] ?? '')) === '') {
                                continue;
                            }
                            // VideoObject injected on publish (TEST ANCHOR, G16-25, ruling 41)
                            $videos[] = [
                                'name' => $block['name'],
                                'contentUrl' => $block['contentUrl'],
                                'uploadDate' => $block['uploadDate'],
                            ];
                        }
                        if (($block['type'] ?? '') === 'faq') {
                            // FAQPage schema injected on publish (TEST ANCHOR, G8-16, ruling 41)
                            $pairs = isset($block['items']) && is_array($block['items']) ? $block['items'] : [$block];
                            foreach ($pairs as $pair) {
                                if (! is_array($pair) || ! is_scalar($pair['question'] ?? '') || ! is_scalar($pair['answer'] ?? '')
                                    || trim((string) ($pair['question'] ?? '')) === '' || trim((string) ($pair['answer'] ?? '')) === '') {
                                    continue;
                                }
                                $faqs[] = ['question' => $pair['question'], 'answer' => $pair['answer']];
                            }
                        }
                    }

                    if ($hasChat) {
                        $chatSrc = route('chat.script', absolute: false);
                        $html .= "<div class=\"chat-widget-container\" data-chat-mount></div>\n";
                        $html .= "<script id=\"x102-chat\" src=\"{$chatSrc}\" data-chat data-key=\"".e($pixelKey)."\"></script>\n";
                    }
                    if ($hasForm) {
                        $definition = app(FormReadAction::class)->firstDefinitionForBusiness($businessId);
                        if ($definition === null) {
                            // No form defined yet: the marker stays so the site law can see the slot, but nothing pretends to be a form.
                            $html .= "<div class=\"form-capture-x155\"></div>\n";
                        } else {
                            $formActionBase = route('x-157.site', ['business' => $businessId, 'deploy_hash' => $deployHash], absolute: false);
                            $html .= "<div class=\"form-capture-x155\">\n";
                            $html .= View::make('x-103::site.blocks.form', [
                                'block' => [
                                    'definition_id' => (int) $definition['id'],
                                    'fields' => $definition['fields'],
                                    'required' => $definition['required'],
                                    'honeypot' => $definition['honeypot'],
                                ],
                                'context' => ['form_action_base' => $formActionBase],
                            ])->render();
                            $html .= "</div>\n";
                        }
                    }
                    if ($hasDni) {
                        $dniUrl = route('x-157.site', ['business' => $businessId, 'deploy_hash' => $deployHash], absolute: false).'/dni';
                        $html .= '<div class="dni-pool-x137" data-dni-url="'.e($dniUrl)."\"></div>\n";
                        $html .= '<script id="x137-dni">'.self::DNI_SCRIPT."</script>\n";
                    }
                }
            }
            $verified = CustomDomainRequest::withoutGlobalScopes()->where('business_id', $businessId)->where('status', 'verified')->orderByDesc('id')->value('domain');
            $canonicalHost = $verified !== null && $verified !== '' ? strtolower($verified) : $zone->domain_name;

            // Where this page's siblings live. On a verified custom domain a root-relative
            // slug is right; at the platform address (the `platform` zone) it would resolve against the platform's
            // own routes, so links go through the stable per-page route (wave 821).
            $linkBase = ($zone->provider === 'platform' && ($verified === null || $verified === '')) ? "/sites/{$businessId}/p" : '';

            if ($x176Usable) {
                $seoResult = app(SeoRenderAction::class)->handle(
                    $businessId,
                    $pageId,
                    $businessName,
                    $commitId,
                    $canonicalHost,
                    pathPrefix: $linkBase
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
            }

            if ($pageId !== null && $businessName !== null && $commitId !== null) {
                $schemaResult = app(SchemaRenderAction::class)->handle(
                    $businessId,
                    $pageId,
                    $businessName,
                    $commitId,
                    $canonicalHost,
                    productOffers: $productOffers ?: null,
                    videos: $videos ?: null,
                    events: $events ?: null,
                    address: $address ?: null,
                    breadcrumbs: $breadcrumbs ?: null,
                    faqs: $faqs ?: null,
                    pathPrefix: $linkBase
                );

                if (isset($schemaResult['json_ld'])) {
                    $html .= "<script type=\"application/ld+json\">\n".json_encode($schemaResult['json_ld'], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)."\n</script>\n";
                }

                $page = app(PageReadAction::class)->findForBusiness($businessId, $pageId);
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

                    $context = [
                        'businessName' => $businessName,
                        'deployHash' => $deployHash,
                        'tenant_storage_url_prefix' => route('x-157.site.media', ['business' => $businessId, 'deploy_hash' => $deployHash], absolute: false).'/',
                        'form_action_base' => route('x-157.site', ['business' => $businessId, 'deploy_hash' => $deployHash], absolute: false),
                        'tokens' => app(IndustryStartingPoints::class)->forBusiness($businessId),
                    ];
                    $html .= app(SiteBlockRenderer::class)->render($contentBlocks, $context);
                }
            }

            if (! empty($breadcrumbs)) {
                $html .= "<nav id=\"breadcrumb-x176\">\n";
                foreach ($breadcrumbs as $crumb) {
                    $html .= '  <a href="'.e($linkBase).'/'.e($crumb['slug']).'">'.e($crumb['name'])."</a>\n";
                }
                $html .= "</nav>\n";
            }

            if (! empty($productOffers)) {
                $html .= "<div id=\"offers-x176\">\n";
                foreach ($productOffers as $offer) {
                    $priceText = e($offer['price_text']).(isset($offer['price_max_text']) ? ' to '.e($offer['price_max_text']) : '');
                    $html .= '  <div class="offer-item" data-name="'.e($offer['name']).'">'.e($offer['name']).' - '.$priceText."</div>\n";
                }
                $html .= "</div>\n";
            }

            if (! empty($events)) {
                $html .= "<div id=\"events-x176\">\n";
                foreach ($events as $event) {
                    $html .= '  <div class="event-item" data-name="'.e($event['name']).'">'.e($event['name']).' - '.e($event['startDate'])."</div>\n";
                }
                $html .= "</div>\n";
            }

            if (! empty($address)) {
                $html .= "<div id=\"address-x176\">\n";
                $html .= '  <div class="address-item"';
                foreach (['line1', 'city', 'region', 'postal_code', 'country'] as $key) {
                    if (isset($address[$key]) && is_string($address[$key]) && $address[$key] !== '') {
                        $html .= ' data-'.str_replace('_', '-', $key).'="'.e($address[$key]).'"';
                    }
                }
                $html .= '>';
                $parts = [];
                foreach (['line1', 'city', 'region', 'postal_code', 'country'] as $key) {
                    if (isset($address[$key]) && is_string($address[$key]) && $address[$key] !== '') {
                        $parts[] = e($address[$key]);
                    }
                }
                $html .= implode(', ', $parts);
                $html .= "</div>\n";
                $html .= "</div>\n";
            }

            $internalLinksHtml = app(InternalLinkRenderAction::class)->handle($businessId, $linkBase);
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
            // an arm never supersedes the other arm; the owner's stop (X-103) is what retires a variant.
            Deployment::where('business_id', $businessId)
                ->where('edge_zone_id', $zone->id)
                ->where('page_id', $pageId)
                ->when($pageVariantId === null, fn ($q) => $q->whereNull('page_variant_id'), fn ($q) => $q->where('page_variant_id', $pageVariantId))
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
                domainName: $canonicalHost,
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
