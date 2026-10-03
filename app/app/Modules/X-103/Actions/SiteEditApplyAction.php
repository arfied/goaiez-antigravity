<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Models\Business;
use App\Modules\X103\Domain\SiteThemes;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Facades\DB;

final class SiteEditApplyAction
{
    public function handle(int $businessId, int $pageId): array
    {
        return DB::transaction(function () use ($businessId, $pageId) {
            $page = Page::where('business_id', $businessId)->findOrFail($pageId);
            $pending = $page->draft_meta['pending_edit'] ?? null;

            if (! $pending) {
                return [
                    'status' => 'refused',
                    'reason' => 'Nothing proposed.',
                    'applied_style' => false,
                ];
            }

            $meta = $page->draft_meta;
            if (! isset($meta['undo'])) {
                $meta['undo'] = [];
            }

            $previousSiteTokens = Business::whereKey($businessId)->value('site_tokens');
            if (is_string($previousSiteTokens)) {
                $previousSiteTokens = json_decode($previousSiteTokens, true);
            }

            $meta['undo'][] = [
                'blocks' => $page->draft_blocks ?? [],
                'site_tokens' => $previousSiteTokens,
            ];

            if (count($meta['undo']) > 20) {
                array_shift($meta['undo']);
            }

            $page->draft_blocks = $pending['blocks'];

            $appliedStyle = false;
            if (isset($pending['style'])) {
                $tokens = is_array($previousSiteTokens) ? $previousSiteTokens : [];
                if (isset($pending['style']['palette'])) {
                    $tokens['palette'] = array_replace($tokens['palette'] ?? [], $pending['style']['palette']);
                }
                if (isset($pending['style']['type_pairing'])) {
                    $tokens['type_pairing'] = array_replace($tokens['type_pairing'] ?? [], $pending['style']['type_pairing']);
                }
                if (is_string($pending['style']['corners'] ?? null)) {
                    $tokens['corners'] = $pending['style']['corners'];
                }
                // An AI design brings its theme with its colours (SiteDesignUseAction always pairs them).
                if (is_string($pending['theme'] ?? null) && SiteThemes::get($pending['theme']) !== null) {
                    $tokens['theme'] = $pending['theme'];
                }
                Business::whereKey($businessId)->update(['site_tokens' => $tokens]);
                $appliedStyle = true;
                $meta['look_changed'] = true;
            }

            unset($meta['pending_edit']);
            $page->draft_meta = $meta;
            $page->save();

            return [
                'status' => 'applied',
                'reason' => null,
                'applied_style' => $appliedStyle,
            ];
        });
    }
}
