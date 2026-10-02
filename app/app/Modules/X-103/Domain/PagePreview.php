<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Modules\X103\Models\Page;
use App\Services\Industry\IndustryStartingPoints;
use Illuminate\Support\Facades\Storage;

final class PagePreview
{
    private const CONTEXT = ['businessName' => '', 'deployHash' => 'weight', 'tenant_storage_url_prefix' => '/m/', 'form_action_base' => '/f'];

    public function html(Page $page, bool $proposed, ?int $selectedIndex = null, bool $editable = false): string
    {
        $blocks = $page->draft_blocks ?? [];
        if ($proposed && isset($page->draft_meta['pending_edit']['blocks'])) {
            $blocks = $page->draft_meta['pending_edit']['blocks'];
        }

        $tokens = app(IndustryStartingPoints::class)->forBusiness($page->business_id);

        if ($proposed && isset($page->draft_meta['pending_edit']['style'])) {
            $style = $page->draft_meta['pending_edit']['style'];
            if (isset($style['palette'])) {
                $tokens['palette'] = array_replace($tokens['palette'], $style['palette']);
            }
            if (isset($style['type_pairing'])) {
                $tokens['type_pairing'] = array_replace($tokens['type_pairing'], $style['type_pairing']);
            }
        }

        $html = app(SiteBlockRenderer::class)->render($blocks, ['tokens' => $tokens, 'editable' => $editable] + self::CONTEXT);

        $disk = Storage::disk('local');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($blocks as $block) {
            $paths = [];
            if (($block['type'] ?? '') === 'hero' && isset($block['image_path'])) {
                $paths[] = $block['image_path'];
            }
            if (($block['type'] ?? '') === 'gallery' && isset($block['items']) && is_array($block['items'])) {
                foreach ($block['items'] as $item) {
                    if (isset($item['image_path'])) {
                        $paths[] = $item['image_path'];
                    }
                }
            }

            foreach ($paths as $path) {
                if (is_string($path) && $disk->exists($path)) {
                    $absPath = $disk->path($path);
                    $mime = @$finfo->file($absPath);
                    if ($mime === 'image/jpeg' || $mime === 'image/png' || $mime === 'image/webp') {
                        $contents = $disk->get($path);
                        if (is_string($contents)) {
                            $base64 = base64_encode($contents);
                            $dataUri = 'data:'.$mime.';base64,'.$base64;
                            $html = str_replace('/m/'.basename($path), $dataUri, $html);
                        }
                    }
                }
            }
        }

        // Preview-only shell. EdgeDeployAction builds its own document and does not call this class, so
        // nothing here can reach a published page. Uses only the custom properties SiteBlockRenderer's
        // own :root already defines — no new token.
        $previewShell = '<style>'
            .'html{background:color-mix(in srgb, var(--color-ink) 18%, var(--color-canvas));}'
            .'body{min-height:100vh;}'
            .'</style>';

        if ($selectedIndex !== null) {
            $needle = 'data-block-index="'.$selectedIndex.'"';
            $html = str_replace($needle, $needle.' data-selected-block="'.$selectedIndex.'"', $html);
            // The outline rule ships ONLY when something is selected, so a preview with no selection
            // contains the string `data-selected-block` nowhere at all. That is what makes the absence
            // assertable, and it is also why no dead rule is shipped.
            $previewShell .= '<style>[data-selected-block]{outline:3px solid var(--color-accent);outline-offset:4px;}</style>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"></head>'
            .'<body data-preview-page="1">'.$html.$previewShell.'</body></html>';
    }

    /** The AI-designed page (draft_meta.design) as its own preview document, the owner's pictures inlined as html() does. */
    public function designHtml(Page $page): string
    {
        $design = $page->draft_meta['design'] ?? null;
        if (! is_array($design) || ($design['status'] ?? null) !== 'ready') {
            return '';
        }

        $html = (string) ($design['html'] ?? '');
        $disk = Storage::disk('local');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        foreach ((array) ($design['images'] ?? []) as $n => $path) {
            $src = '';
            if (is_string($path) && $disk->exists($path)) {
                $mime = @$finfo->file($disk->path($path));
                if ($mime === 'image/jpeg' || $mime === 'image/png' || $mime === 'image/webp') {
                    $src = 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
                }
            }
            $html = str_replace('[[image:'.$n.']]', $src, $html);
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base target="_blank">'
            .'<style>'.(string) ($design['style'] ?? '').'</style></head>'
            .'<body data-preview-design="1">'.$html.'</body></html>';
    }
}
