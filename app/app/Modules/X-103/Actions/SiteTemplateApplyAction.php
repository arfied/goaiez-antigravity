<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Puts a site template on the business's site (SiteTemplates). The template's colours and fonts become the starting point,
 * and the template replaces any theme: the two are different looks over the same sections, never layered. The old look goes
 * on the page's undo stack in the same {blocks, site_tokens} shape as every other Studio change, and the page is marked
 * look_changed so the Studio offers Publish even though no section changed. Applying a theme afterwards (SiteThemeApplyAction)
 * writes site_tokens whole, so it takes the template off again.
 */
final class SiteTemplateApplyAction
{
    /**
     * @return array{status: string, reason?: string, template?: string}
     */
    public function handle(int $businessId, int $pageId, string $templateId): array
    {
        $template = SiteTemplates::get($templateId);
        if ($template === null) {
            return ['status' => 'refused', 'reason' => 'unknown_template'];
        }

        return DB::transaction(function () use ($businessId, $pageId, $template) {
            $page = Page::where('business_id', $businessId)->findOrFail($pageId);

            $previousSiteTokens = Business::whereKey($businessId)->value('site_tokens');
            if (is_string($previousSiteTokens)) {
                $previousSiteTokens = json_decode($previousSiteTokens, true);
            }

            $meta = $page->draft_meta ?? [];
            $meta['undo'] = $meta['undo'] ?? [];
            $meta['undo'][] = ['blocks' => $page->draft_blocks ?? [], 'site_tokens' => $previousSiteTokens];
            if (count($meta['undo']) > 20) {
                array_shift($meta['undo']);
            }
            $meta['look_changed'] = true;
            $page->draft_meta = $meta;
            $page->save();

            Business::whereKey($businessId)->update(['site_tokens' => json_encode([
                'template' => $template['id'],
                'palette' => $template['palette'],
                'type_pairing' => $template['type_pairing'],
            ], JSON_THROW_ON_ERROR)]);

            return ['status' => 'applied', 'template' => $template['id']];
        });
    }
}
