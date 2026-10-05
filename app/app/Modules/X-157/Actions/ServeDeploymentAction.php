<?php

namespace App\Modules\X157\Actions;

use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X157\Models\Deployment;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;

class ServeDeploymentAction
{
    /** The latest deployed control arm of the published page with this slug, at the platform address (wave 821). */
    public function latestPage(int $businessId, string $slug)
    {
        Tenancy::set($businessId);

        $slug = trim($slug, '/') === '' ? 'home' : trim($slug, '/');
        $page = app(PageReadAction::class)->publishedForSlugs($businessId, [$slug])->first();
        abort_if($page === null, 404);

        $deployment = Deployment::where('business_id', $businessId)
            ->where('status', 'deployed')
            ->where('page_id', (int) $page->id)
            ->whereNull('page_variant_id')
            ->whereHas('edgeZone', fn ($q) => $q->where('has_valid_ssl', true))
            ->latest('id')
            ->first();
        abort_if($deployment === null, 404);

        return $this->page($businessId, (string) $deployment->deploy_hash);
    }

    public function page(int $businessId, string $deployHash)
    {
        Tenancy::set($businessId);

        $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
        abort_if($deployment->status !== 'deployed', 404);

        $zone = $deployment->edgeZone;
        abort_if($zone === null || ! $zone->has_valid_ssl, 404);

        $html = Storage::disk('local')->get("sites/{$deployHash}.html");
        abort_if($html === null, 404);

        Deployment::whereKey($deployment->id)->increment('served_count');

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    public function sitemap(int $businessId, string $deployHash, ?string $customHost = null)
    {
        Tenancy::set($businessId);

        $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
        abort_if($deployment->status !== 'deployed', 404);

        $zone = $deployment->edgeZone;
        abort_if($zone === null || ! $zone->has_valid_ssl, 404);

        $xml = app(SitemapRenderAction::class)->handle($businessId, $customHost);

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }

    public function robots(int $businessId, string $deployHash, ?string $customHost = null)
    {
        Tenancy::set($businessId);

        $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
        abort_if($deployment->status !== 'deployed', 404);

        $zone = $deployment->edgeZone;
        abort_if($zone === null || ! $zone->has_valid_ssl, 404);

        $sitemapUrl = route('x-157.site', ['business' => $businessId, 'deploy_hash' => $deployHash]).'/sitemap.xml';

        // On a verified custom domain the whole host is the site, and its sitemap is the one that domain serves itself — a search
        // engine only trusts a sitemap on another host when both are verified to the same owner (owner, 2026-10-05).
        if ($customHost !== null) {
            $txt = "User-agent: *\n";
            $txt .= "Allow: /\n";
            $txt .= "Disallow: /sites/{$businessId}/{$deployHash}/dni\n";
            $txt .= "Disallow: /sites/{$businessId}/{$deployHash}/forms/\n";
            $txt .= "Sitemap: https://{$customHost}/sitemap.xml\n";

            return response($txt, 200)->header('Content-Type', 'text/plain');
        }

        $txt = "User-agent: *\n";
        $txt .= "Allow: /sites/{$businessId}/\n";
        $txt .= "Disallow: /sites/{$businessId}/{$deployHash}/dni\n";
        $txt .= "Disallow: /sites/{$businessId}/{$deployHash}/forms/\n";
        $txt .= "Sitemap: {$sitemapUrl}\n";

        return response($txt, 200)->header('Content-Type', 'text/plain');
    }

    public function llms(int $businessId, string $deployHash)
    {
        Tenancy::set($businessId);

        $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
        abort_if($deployment->status !== 'deployed', 404);

        $zone = $deployment->edgeZone;
        abort_if($zone === null || ! $zone->has_valid_ssl, 404);

        $txt = Storage::disk('local')->get("sites/{$deployHash}.llms.txt");
        abort_if($txt === null, 404);

        return response($txt, 200)->header('Content-Type', 'text/plain');
    }
}
