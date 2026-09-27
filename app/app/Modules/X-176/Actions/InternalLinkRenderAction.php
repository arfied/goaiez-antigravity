<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

use App\Modules\X103\Actions\PageReadAction;
use App\Services\Config\DefaultsRegistry;

final class InternalLinkRenderAction
{
    public const EMITTED_MAX = 20;

    /**
     * (R245) Render internal link graph for the given business.
     */
    private function emittedMax(): int
    {
        return $this->registry->int('links.internal.emitted_max');
    }

    public function __construct(private DefaultsRegistry $registry) {}

    public function handle(int $businessId, string $linkBase = ''): string
    {
        $pages = app(PageReadAction::class)->publishedFor($businessId);

        if ($pages->isEmpty()) {
            return '';
        }

        $hierarchyPages = [];
        $collidingKeys = [];

        foreach ($pages as $p) {
            $norm = trim((string) $p->slug, '/');
            if (isset($hierarchyPages[$norm])) {
                $collidingKeys[$norm] = true;
            }
            $hierarchyPages[$norm] = $p;
        }

        $usablePages = [];

        foreach ($pages as $page) {
            $slug = trim((string) $page->slug, '/');

            if ($slug === '') {
                if (! isset($collidingKeys['']) && trim((string) $page->title) !== '') {
                    $usablePages[] = $page;
                }

                continue;
            }

            $parts = explode('/', $slug);
            $usable = true;
            $current = '';

            foreach ($parts as $part) {
                $current = $current ? $current.'/'.$part : $part;
                if (isset($collidingKeys[$current]) || ! isset($hierarchyPages[$current]) || trim((string) $hierarchyPages[$current]->title) === '') {
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

        $emittedCount = 0;
        $renderTree = function (array $nodes) use (&$renderTree, &$emittedCount, $linkBase): string {
            if (empty($nodes) || $emittedCount >= $this->emittedMax()) {
                return '';
            }

            $itemsHtml = '';
            foreach ($nodes as $node) {
                if ($emittedCount >= $this->emittedMax()) {
                    break;
                }
                $emittedCount++;
                $page = $node->page;
                $href = $linkBase.'/'.ltrim((string) $page->slug, '/');
                $itemsHtml .= '<li><a href="'.htmlspecialchars($href, ENT_QUOTES).'">'.htmlspecialchars((string) $page->title, ENT_QUOTES).'</a>';
                $itemsHtml .= $renderTree($node->children);
                $itemsHtml .= '</li>';
            }

            if ($itemsHtml === '') {
                return '';
            }

            return '<ul>'.$itemsHtml.'</ul>';
        };

        $html = '<nav id="internal-links-x176">';
        $html .= $renderTree($tree);
        $html .= '</nav>';

        return $html."\n";
    }
}
