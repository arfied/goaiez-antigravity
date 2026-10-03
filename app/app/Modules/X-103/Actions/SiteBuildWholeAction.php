<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\SiteDesignEngines;
use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use App\Services\Industry\IndustryStartingPoints;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * Builds a whole site with one AI — for a business with no website as much as for one with an old one (the boss,
 * 2026-10-02: "you should be able to make a nice site for people that don't have a site").
 *
 * Creates whichever of Home, Services, About and Contact are missing, then designs them one after another: Home first,
 * free to choose the theme; the others keep the site's theme so the site looks like one brand. An empty page is filled
 * with its design; a page that already has sections only gets a design the owner may use. Nothing is published.
 */
final class SiteBuildWholeAction
{
    public const PAGES = ['home' => 'Home', 'services' => 'Services', 'about' => 'About', 'contact' => 'Contact'];

    public function __construct(private readonly PageCreateAction $create) {}

    /**
     * @return array{status: string, reason?: string, created?: list<string>, home_id?: int}
     */
    public function handle(int $businessId, string $engine = 'claude'): array
    {
        if (! isset(SiteDesignEngines::ENGINES[$engine])) {
            return ['status' => 'refused', 'reason' => 'unknown_engine'];
        }

        $pages = [];
        $created = [];
        foreach (self::PAGES as $slug => $title) {
            $page = Page::where('business_id', $businessId)->where('slug', $slug)->first();
            if ($page === null) {
                $page = $this->create->handle($businessId, $slug, $title);
                $created[] = $slug;
            }
            $pages[$slug] = $page;
        }

        foreach ($pages as $page) {
            $design = $page->draft_meta['designs'][$engine] ?? null;
            $requestedAt = is_array($design) && is_string($design['requested_at'] ?? null) ? Carbon::parse($design['requested_at']) : null;
            if (is_array($design) && ($design['status'] ?? null) === 'running' && $requestedAt !== null && $requestedAt->gt(now()->subMinutes(10))) {
                return ['status' => 'refused', 'reason' => 'already_running'];
            }
        }

        $hasTheme = is_string(app(IndustryStartingPoints::class)->forBusiness($businessId)['theme'] ?? null);
        $jobs = [];
        foreach ($pages as $slug => $page) {
            $meta = $page->draft_meta ?? [];
            $meta['designs'][$engine] = ['status' => 'running', 'requested_at' => now()->toIso8601String()];
            $page->draft_meta = $meta;
            $page->save();
            // Home picks the theme unless the site already has one; every other page keeps it.
            $jobs[] = new SiteDesignJob($businessId, (int) $page->id, $engine, true, $slug !== 'home' || $hasTheme);
        }

        Bus::chain($jobs)->dispatch();

        return ['status' => 'queued', 'created' => $created, 'home_id' => (int) $pages['home']->id];
    }
}
