<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

use App\Modules\X103\Models\Page;

final class InternalLinkRenderAction
{
    /**
     * (R245) Render internal link graph for the given business.
     */
    public function handle(int $businessId): string
    {
        $pages = Page::where('business_id', $businessId)
            ->where('is_published', true)
            ->get();

        if ($pages->isEmpty()) {
            return '';
        }

        $hierarchyPages = $pages->keyBy('slug');
        $usablePages = [];

        foreach ($pages as $page) {
            $slug = trim((string) $page->slug, '/');

            if ($slug === '') {
                if (! empty($page->title)) {
                    $usablePages[] = $page;
                }

                continue;
            }

            $parts = explode('/', $slug);
            $usable = true;
            $current = '';

            foreach ($parts as $part) {
                $current = $current ? $current.'/'.$part : $part;
                if (! isset($hierarchyPages[$current]) || empty($hierarchyPages[$current]->title)) {
                    $usable = false;
                    break;
                }
            }

            if ($usable) {
                $usablePages[] = $page;
            }
        }

        if (count($usablePages) < 2) {
            return '';
        }

        $html = '<nav id="internal-links-x176">';
        foreach ($usablePages as $page) {
            $href = '/'.ltrim((string) $page->slug, '/');
            $html .= '<a href="'.htmlspecialchars($href, ENT_QUOTES).'">'.htmlspecialchars((string) $page->title, ENT_QUOTES).'</a>';
        }
        $html .= '</nav>';

        return $html."\n";
    }
}
