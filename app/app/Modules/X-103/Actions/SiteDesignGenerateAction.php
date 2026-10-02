<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\AiTask;
use App\Modules\X103\Domain\SiteHtmlSanitizer;
use App\Modules\X103\Domain\StatedFacts;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Config\DefaultsRegistry;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Visibility\CompetitorSiteNotes;
use Throwable;

/**
 * The AI designer (prototype, 2026-10-02 — the boss: "if we ask an AI to build a nice website in chat it can").
 *
 * A strong model designs the WHOLE page — layout, styling, typography, icons — from the page's current content, the
 * owner's stated facts, the crawled site and what the top nearby businesses' sites cover (reference only — a business
 * with no website of its own still gets a full page), instead of filling twelve fixed section templates. The designer
 * may ask for up to three new pictures ([[new:description]]), which the image model makes for this business. Its answer is cleaned
 * by SiteHtmlSanitizer (no script, nothing loaded from outside, pictures only as the owner's own [[image:N]]) and kept
 * on the page as draft_meta.design. The Studio shows it beside the current page; this action never publishes anything.
 */
final class SiteDesignGenerateAction
{
    public function __construct(
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
        private readonly StatedFacts $statedFacts,
        private readonly SiteHtmlSanitizer $sanitizer,
        private readonly CompetitorSiteNotes $peers,
        private readonly SiteImageGenerateAction $picture,
    ) {}

    /**
     * @return array{status: string, reason?: string, model?: string}
     */
    public function handle(int $businessId, int $pageId): array
    {
        $page = Page::where('business_id', $businessId)->findOrFail($pageId);
        $blocks = is_array($page->draft_blocks) ? $page->draft_blocks : [];

        // The owner's own pictures, numbered. The model only ever sees the token, never a file path.
        $images = [];
        $imageLines = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $found = [];
            if (($block['type'] ?? '') === 'hero' && is_string($block['image_path'] ?? null) && $block['image_path'] !== '') {
                $found[] = [$block['image_path'], is_scalar($block['image_alt'] ?? null) ? (string) $block['image_alt'] : 'main picture'];
            }
            if (($block['type'] ?? '') === 'gallery' && is_array($block['items'] ?? null)) {
                foreach ($block['items'] as $item) {
                    if (is_array($item) && is_string($item['image_path'] ?? null) && $item['image_path'] !== '') {
                        $found[] = [$item['image_path'], is_scalar($item['alt'] ?? null) ? (string) $item['alt'] : 'photo of the work'];
                    }
                }
            }
            foreach ($found as [$path, $alt]) {
                if (count($images) < 12) {
                    $n = count($images) + 1;
                    $images[$n] = $path;
                    $imageLines[] = '[[image:'.$n.']] — '.$alt;
                }
            }
        }

        // Words only: file paths, sizes and bookkeeping are taken out before the model sees the page.
        $content = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            unset($block['image_path'], $block['image_width'], $block['image_height'], $block['source'], $block['model'], $block['peers']);
            if (is_array($block['items'] ?? null)) {
                $items = [];
                foreach ($block['items'] as $item) {
                    if (is_array($item)) {
                        unset($item['image_path'], $item['width'], $item['height']);
                        $items[] = $item;
                    }
                }
                $block['items'] = $items;
            }
            $content[] = $block;
        }

        $crawled = '';
        foreach (SiteInventoryPage::where('business_id', $businessId)->orderBy('id')->limit(8)->get() as $inventoryPage) {
            $text = trim((string) $inventoryPage->text);
            if ($text !== '') {
                $crawled .= "\n--- ".(string) $inventoryPage->url."\n".mb_substr($text, 0, 1500);
            }
        }
        $crawled = mb_substr($crawled, 0, 9000);

        $tokens = app(IndustryStartingPoints::class)->forBusiness($businessId);
        $peerNotes = $this->peers->referenceBlock($businessId);

        $prompt = $this->statedFacts->section($businessId)
            ."\n\nPage title: ".(string) $page->title
            ."\n\nThis page's current content (JSON):\n".json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ."\n\nPictures you may use, as the img src exactly as written:\n".($imageLines === [] ? '(none yet — ask for new ones with [[new:description]])' : implode("\n", $imageLines))
            ."\n\nThe brand's starting colours and fonts (refine them as you like): ".json_encode(['palette' => $tokens['palette'] ?? [], 'type_pairing' => $tokens['type_pairing'] ?? []], JSON_UNESCAPED_SLASHES)
            .($crawled === '' ? '' : "\n\nText from the business's current website, for reference only:".$crawled)
            .($peerNotes === '' ? '' : "\n\n".$peerNotes);

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::SiteDesign,
            prompt: $prompt,
            system: $this->registry->string('sites.design.system_prompt'),
        ));

        if (! $response->isUsable() || ! is_string($response->text)) {
            return $this->fail($page, (string) ($response->failureReason ?? $response->refusalCategory ?? 'unknown'));
        }

        $raw = $response->text;
        $end = strripos($raw, '</main>');
        if ($end === false) {
            // No closing </main>: the answer was cut off, or is not a page. Half a page is never shown.
            return $this->fail($page, 'cut_off');
        }
        $starts = array_values(array_filter([stripos($raw, '<style'), stripos($raw, '<main')], fn ($p) => $p !== false));
        $start = $starts === [] ? 0 : min($starts);
        $pageHtml = substr($raw, $start, $end + 7 - $start);

        // New pictures the designer asked for: at most three, made by the image model for this business. A picture
        // that cannot be made leaves an empty src, and the sanitizer then drops that <img>.
        $made = 0;
        $pageHtml = (string) preg_replace_callback('#\[\[new:([^\]]{3,300})\]\]#', function (array $m) use (&$images, &$made, $businessId): string {
            if ($made >= 3 || count($images) >= 15) {
                return '';
            }
            $made++;
            try {
                $res = $this->picture->handle($businessId, trim($m[1]));
            } catch (Throwable) {
                return '';
            }
            if (($res['status'] ?? null) !== 'generated' || ! is_string($res['path'] ?? null)) {
                return '';
            }
            $n = count($images) + 1;
            $images[$n] = $res['path'];

            return '[[image:'.$n.']]';
        }, $pageHtml);

        $clean = $this->sanitizer->clean($pageHtml);

        if (trim(strip_tags($clean['html'])) === '') {
            return $this->fail($page, 'empty');
        }

        $page->refresh();
        $meta = $page->draft_meta ?? [];
        $meta['design'] = [
            'status' => 'ready',
            'style' => $clean['style'],
            'html' => $clean['html'],
            'images' => $images,
            'model' => $response->model->value,
            'drafted_at' => now()->toIso8601String(),
        ];
        $page->draft_meta = $meta;
        $page->save();

        return ['status' => 'ready', 'model' => $response->model->value];
    }

    /**
     * @return array{status: string, reason: string}
     */
    public function fail(Page $page, string $reason): array
    {
        $page->refresh();
        $meta = $page->draft_meta ?? [];
        $meta['design'] = ['status' => 'failed', 'reason' => $reason, 'failed_at' => now()->toIso8601String()];
        $page->draft_meta = $meta;
        $page->save();

        return ['status' => 'failed', 'reason' => $reason];
    }
}
