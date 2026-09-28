<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Modules\X103\Models\Page;
use App\Services\Industry\IndustryStartingPoints;

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
        $html = app(SiteBlockRenderer::class)->render($blocks, ['tokens' => $tokens] + self::CONTEXT);

        return '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"></head><body>'.$html.'</body></html>';
    }
}
