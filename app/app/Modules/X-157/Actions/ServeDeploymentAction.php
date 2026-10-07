<?php

namespace App\Modules\X157\Actions;

use App\Modules\X103\Actions\PageReadAction;
use App\Modules\X157\Models\Deployment;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;

class ServeDeploymentAction
{
    /** Extension → Content-Type for a static artifact. The deploy action accepts ONLY these extensions, so the two cannot drift. */
    public const MIME = [
        'html' => 'text/html', 'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript', 'json' => 'application/json',
        'map' => 'application/json', 'webmanifest' => 'application/manifest+json', 'txt' => 'text/plain', 'xml' => 'application/xml',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'ttf' => 'font/ttf', 'otf' => 'font/otf', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf',
    ];

    public function file(int $businessId, string $deployHash, string $path)
    {
        Tenancy::set($businessId);

        $deployment = Deployment::where('business_id', $businessId)->where('deploy_hash', $deployHash)->firstOrFail();
        abort_if($deployment->status !== 'deployed', 404);

        $zone = $deployment->edgeZone;
        abort_if($zone === null || ! $zone->has_valid_ssl, 404);

        abort_if($deployment->kind !== StaticSiteDeployAction::KIND, 404);

        $path = trim($path, '/');
        if ($path === '') {
            $path = 'index.html';
        }

        $components = explode('/', $path);
        foreach ($components as $component) {
            abort_if(! preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $component), 404);
        }

        if (! str_contains(end($components), '.')) {
            $path .= '/index.html';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_if(! array_key_exists($ext, self::MIME), 404);

        $bytes = Storage::disk('local')->get("sites-static/{$deployHash}/{$path}");
        abort_if($bytes === null, 404);

        if ($ext === 'html') {
            Deployment::whereKey($deployment->id)->increment('served_count');
            $cache = 'no-cache';
        } elseif (str_starts_with($path, 'assets/')) {
            $cache = 'public, max-age=31536000, immutable';
        } else {
            $cache = 'public, max-age=300';
        }

        return response($bytes, 200)->header('Content-Type', self::MIME[$ext])->header('Cache-Control', $cache);
    }

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

        if ($deployment->kind === StaticSiteDeployAction::KIND) {
            return $this->file($businessId, $deployHash, '');
        }

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

        if ($deployment->kind === StaticSiteDeployAction::KIND) {
            if (Storage::disk('local')->exists("sites-static/{$deployHash}/sitemap.xml")) {
                return $this->file($businessId, $deployHash, 'sitemap.xml');
            }
        }

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

        if ($deployment->kind === StaticSiteDeployAction::KIND) {
            if (Storage::disk('local')->exists("sites-static/{$deployHash}/llms.txt")) {
                return $this->file($businessId, $deployHash, 'llms.txt');
            }
        }

        $txt = Storage::disk('local')->get("sites/{$deployHash}.llms.txt");
        abort_if($txt === null, 404);

        return response($txt, 200)->header('Content-Type', 'text/plain');
    }
}
