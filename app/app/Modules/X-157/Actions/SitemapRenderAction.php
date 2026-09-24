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
            if ($customHost !== null && $deployment->page_id) {
                $page = app(PageReadAction::class)->findForBusiness($businessId, (int) $deployment->page_id);
                if ($page) {
                    $slug = trim($page->slug, '/');
                    $loc = 'https://'.$customHost.'/'.($slug === 'home' ? '' : $slug);
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
