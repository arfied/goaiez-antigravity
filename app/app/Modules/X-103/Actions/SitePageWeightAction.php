<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryImage;

/**
 * How heavy each drafted page is, from data already in hand — no fetch, no
 * timing. Pictures are the stored inventory images the page's hero and gallery
 * point at (an exact join on `path`); the page text is the block HTML rendered
 * in-process against a fixed placeholder context (so the number never moves
 * with a deploy hash); outside scripts is the visitor pixel, 0 or 1, the only
 * external script the deployed page links. It is a WEIGHT in bytes, never a
 * load time: the pixel measures real visitors' load times, this does not.
 *
 * @return list<array{page: string, images: int, image_bytes: int, largest: ?string, largest_bytes: int, html_bytes: int, scripts: int}>
 */
final class SitePageWeightAction
{
    private const CONTEXT = ['businessName' => '', 'deployHash' => 'weight', 'tenant_storage_url_prefix' => '/m/', 'form_action_base' => '/f'];

    public function __construct(private readonly SiteBlockRenderer $renderer) {}

    public function handle(int $businessId): array
    {
        $rows = [];
        foreach (Page::where('business_id', $businessId)->orderBy('id')->get() as $page) {
            $blocks = is_array($page->draft_blocks) ? $page->draft_blocks : [];
            $paths = [];
            $types = [];
            foreach ($blocks as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $types[] = (string) ($block['type'] ?? '');
                if (($block['type'] ?? null) === 'hero' && $this->present($block, 'image_path')) {
                    $paths[] = (string) $block['image_path'];
                }
                if (($block['type'] ?? null) === 'gallery') {
                    foreach ((array) ($block['items'] ?? []) as $item) {
                        if (is_array($item) && $this->present($item, 'image_path')) {
                            $paths[] = (string) $item['image_path'];
                        }
                    }
                }
            }
            $paths = array_values(array_unique($paths));
            $images = $paths === [] ? collect() : SiteInventoryImage::query()
                ->where('business_id', $businessId)
                ->where('status', 'stored')
                ->whereIn('path', $paths)
                ->get(['path', 'bytes']);
            $largest = $images->sortByDesc('bytes')->first();
            $rows[] = [
                'page' => (string) $page->slug,
                'images' => count($paths),
                'image_bytes' => (int) $images->sum('bytes'),
                'largest' => $largest ? basename((string) $largest->path) : null,
                'largest_bytes' => $largest ? (int) $largest->bytes : 0,
                'html_bytes' => strlen($this->renderer->render($blocks, self::CONTEXT)),
                'scripts' => in_array('pixel_script', $types, true) ? 1 : 0,
            ];
        }

        return $rows;
    }

    private function present(array $a, string $key): bool
    {
        return isset($a[$key]) && is_scalar($a[$key]) && trim((string) $a[$key]) !== '';
    }
}
