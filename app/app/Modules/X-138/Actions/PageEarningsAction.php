<?php

declare(strict_types=1);

namespace App\Modules\X138\Actions;

use App\Modules\X103\Actions\PageReadAction;
use App\Services\Warehouse\PageTraffic;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * Per published page of the built site, what the pixel measured over a window:
 * pageviews and conversions from the page mart, keyed on the path the page is
 * served at (REVIEWS-43: "/" for home, "/<slug>" otherwise). Nothing here is an
 * argument somebody must pass; a page with no rows reads 0 only once the pixel
 * has measured this tenant at all — before that, `measured` is false and the
 * screen says so instead of printing zeros.
 *
 * @return array{days: int, measured: bool, pages: list<array{slug: string, title: string, path: string, pageviews: int, conversions: int}>}
 */
final class PageEarningsAction
{
    public function __construct(
        private readonly PageReadAction $pages,
        private readonly PageTraffic $traffic,
    ) {}

    public function handle(int $businessId, int $days = 30): array
    {
        Tenancy::idOrFail();
        $to = CarbonImmutable::now();
        $from = $to->subDays($days);
        $measured = $this->traffic->hasEverMeasured();

        $rows = [];
        foreach ($this->pages->publishedFor($businessId) as $page) {
            $slug = trim((string) $page->slug, '/');
            $path = $slug === '' || $slug === 'home' ? '/' : '/'.$slug;
            $totals = $measured ? $this->traffic->forPage($path, $from, $to) : null;
            $rows[] = [
                'slug' => $slug,
                'title' => (string) $page->title,
                'path' => $path,
                'pageviews' => $totals !== null ? $totals->pageviews : 0,
                'conversions' => $totals !== null ? $totals->conversions : 0,
            ];
        }

        return ['days' => $days, 'measured' => $measured, 'pages' => $rows];
    }
}
