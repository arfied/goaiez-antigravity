<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Domain\SectionOrder;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Models\Page;
use App\Services\Industry\IndustryResolver;
use App\Services\Industry\IndustryStartingPoints;

final class SitePreviewAction
{
    private const CONTEXT = ['businessName' => '', 'deployHash' => 'weight', 'tenant_storage_url_prefix' => '/m/', 'form_action_base' => '/f'];

    public function __construct(
        private readonly PageReadAction $pages,
        private readonly IndustryStartingPoints $startingPoints,
        private readonly IndustryResolver $industryResolver,
        private readonly SiteBlockRenderer $renderer
    ) {}

    public function handle(int $businessId): ?array
    {
        $page = $this->pages->homeFor($businessId) ?? Page::where('business_id', $businessId)->orderBy('id')->first();

        if ($page === null) {
            return null;
        }

        $family = $this->industryResolver->for($businessId)['family'];
        $baseSp = $this->startingPoints->for($family);

        $previews = [];
        foreach (IndustryStartingPoints::VARIANTS as $variant) {
            $variantTokens = $this->startingPoints->variant($baseSp, $variant);
            $blocks = SectionOrder::apply($page->draft_blocks ?? [], $variantTokens['section_order']);

            $html = $this->renderer->render($blocks, ['tokens' => $variantTokens] + self::CONTEXT);
            $previews[$variant] = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"></head><body>'.$html.'</body></html>';
        }

        return $previews;
    }
}
