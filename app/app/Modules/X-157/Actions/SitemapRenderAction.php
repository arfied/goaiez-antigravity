<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X157\Models\Deployment;
use App\Services\Config\DefaultsRegistry;

class SitemapRenderAction
{
    public function handle(int $businessId, ?string $customHost = null): string
    {
        $maxUrls = app(DefaultsRegistry::class)->int('sites.sitemap.max_urls');

        $deployments = Deployment::where('business_id', $businessId)
            ->where('status', 'deployed')
            ->whereNull('page_variant_id')
            ->latest('id')
            ->get()
            ->unique('page_id')
            ->take($maxUrls);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($deployments as $deployment) {
            $loc = route('x-157.site', ['business' => $businessId, 'deploy_hash' => $deployment->deploy_hash]);
            $page = $deployment->page_id ? app(PageReadAction::class)->findForBusiness($businessId, (int) $deployment->page_id) : null;
            if ($page) {
                $slug = trim((string) $page->slug, '/');
                if ($customHost !== null) {
                    $loc = 'https://'.$customHost.'/'.($slug === 'home' ? '' : $slug);
                } elseif ($deployment->edgeZone?->provider === 'platform') {
                    // The stable per-page route (wave 821): a hash URL changes on every
                    // publish, so a crawler's copy of this file would go stale (wave 822).
                    $loc = url("/sites/{$businessId}/p/".($slug === '' ? 'home' : $slug));
                }
            }

            $lastmod = ($deployment->deployed_at ?? $deployment->updated_at ?? now())->toW3cString();

            $xml .= '    <url>'."\n";
            $xml .= '        <loc>'.e($loc).'</loc>'."\n";
            $xml .= '        <lastmod>'.e($lastmod).'</lastmod>'."\n";
            $xml .= '    </url>'."\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
