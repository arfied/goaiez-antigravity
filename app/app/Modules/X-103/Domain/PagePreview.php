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

        // A proposal can carry a theme (an AI design, SiteDesignUseAction): preview it with that theme.
        if ($proposed && is_string($page->draft_meta['pending_edit']['theme'] ?? null)) {
            $tokens['theme'] = $page->draft_meta['pending_edit']['theme'];
        }

        if ($proposed && isset($page->draft_meta['pending_edit']['style'])) {
            $style = $page->draft_meta['pending_edit']['style'];
            if (isset($style['palette'])) {
                $tokens['palette'] = array_replace($tokens['palette'], $style['palette']);
            }
            if (isset($style['type_pairing'])) {
                $tokens['type_pairing'] = array_replace($tokens['type_pairing'], $style['type_pairing']);
            }
            if (is_string($style['corners'] ?? null)) {
                $tokens['corners'] = $style['corners'];
            }
        }

        return $this->document($blocks, $tokens, $selectedIndex, $editable);
    }

    /**
     * One AI's design of the page (draft_meta.designs.<engine>): its sections drawn by our renderer with its theme and
     * colours — exactly what the page will look like if the owner uses it.
     */
    public function designHtml(Page $page, string $engine): string
    {
        $design = $page->draft_meta['designs'][$engine] ?? null;
        if (! is_array($design) || ($design['status'] ?? null) !== 'ready' || ! is_array($design['blocks'] ?? null)) {
            return '';
        }

        $tokens = app(IndustryStartingPoints::class)->forBusiness($page->business_id);
        $theme = is_string($design['theme'] ?? null) ? SiteThemes::get($design['theme']) : null;
        if ($theme !== null) {
            $tokens['theme'] = $theme['id'];
            $tokens['palette'] = array_replace($tokens['palette'], $theme['palette']);
            $tokens['type_pairing'] = array_replace($tokens['type_pairing'], $theme['type_pairing']);
        }
        $style = is_array($design['style'] ?? null) ? $design['style'] : [];
        if (isset($style['palette']) && is_array($style['palette'])) {
            $tokens['palette'] = array_replace($tokens['palette'], $style['palette']);
        }
        if (isset($style['type_pairing']) && is_array($style['type_pairing'])) {
            $tokens['type_pairing'] = array_replace($tokens['type_pairing'], $style['type_pairing']);
        }
        if (is_string($style['corners'] ?? null)) {
            $tokens['corners'] = $style['corners'];
        }

        return $this->document($design['blocks'], $tokens, null, false);
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<string, mixed>  $tokens
     */
    private function document(array $blocks, array $tokens, ?int $selectedIndex, bool $editable): string
    {
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
}
