<?php

namespace App\Modules\X157\Actions;

use App\Modules\X157\Models\Deployment;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;

class ServeDeploymentAction
{
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

    public function robots(int $businessId, string $deployHash)
    {
        Tenancy::set($businessId);

        $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
        abort_if($deployment->status !== 'deployed', 404);

        $zone = $deployment->edgeZone;
        abort_if($zone === null || ! $zone->has_valid_ssl, 404);

        $sitemapUrl = route('x-157.site', ['business' => $businessId, 'deploy_hash' => $deployHash]).'/sitemap.xml';

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
