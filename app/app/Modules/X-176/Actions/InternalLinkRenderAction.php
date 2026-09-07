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
            ->orderBy('slug', 'asc')
            ->get();

        if ($pages->isEmpty()) {
            return '';
        }

        $hierarchyPages = $pages->keyBy(function ($page) {
            return trim((string) $page->slug, '/');
        });
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

        $usablePages = array_slice($usablePages, 0, 20);

        if (count($usablePages) < 2) {
            return '';
        }

        $nodes = [];
        foreach ($usablePages as $page) {
            $slug = trim((string) $page->slug, '/');
            $nodes[$slug] = (object) ['page' => $page, 'children' => []];
        }

        $tree = [];
        foreach ($nodes as $slug => $node) {
            if ($slug === '') {
                $tree[] = $node;
            } else {
                $parts = explode('/', $slug);
                array_pop($parts);
                $parentSlug = implode('/', $parts);
                if (isset($nodes[$parentSlug])) {
                    $nodes[$parentSlug]->children[] = $node;
                } else {
                    $tree[] = $node;
                }
            }
        }

        $renderTree = function (array $nodes) use (&$renderTree): string {
            if (empty($nodes)) {
                return '';
            }
            $html = '<ul>';
            foreach ($nodes as $node) {
                $page = $node->page;
                $href = '/'.ltrim((string) $page->slug, '/');
                $html .= '<li><a href="'.htmlspecialchars($href, ENT_QUOTES).'">'.htmlspecialchars((string) $page->title, ENT_QUOTES).'</a>';
                $html .= $renderTree($node->children);
                $html .= '</li>';
            }
            $html .= '</ul>';

            return $html;
        };

        $html = '<nav id="internal-links-x176">';
        $html .= $renderTree($tree);
        $html .= '</nav>';

        return $html."\n";
    }
}
