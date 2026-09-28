<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Modules\X103\Models\Page;
use App\Services\Industry\IndustryStartingPoints;
use Illuminate\Support\Facades\Storage;

final class PagePreview
{
    private const CONTEXT = ['businessName' => '', 'deployHash' => 'weight', 'tenant_storage_url_prefix' => '/m/', 'form_action_base' => '/f'];

    public function html(Page $page, bool $proposed): string
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

        $html = app(SiteBlockRenderer::class)->render($blocks, ['tokens' => $tokens] + self::CONTEXT);

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

        return '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"></head><body>'.$html.'</body></html>';
    }
}
