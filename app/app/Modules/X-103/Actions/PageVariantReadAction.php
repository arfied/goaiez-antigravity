<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Modules\X103\Models\PageVariant;

class PageVariantReadAction
{
    /**
     * @return array{id: int, control_hash: string, variant_hash: string, control_headline: string, variant_headline: string}|null
     */
    public function runningFor(int $businessId, int $pageId): ?array
    {
        $row = PageVariant::where('business_id', $businessId)
            ->where('page_id', $pageId)
            ->where('status', 'running')
            ->latest('id')
            ->first();

        if (! $row || ! $row->control_deploy_hash || ! $row->variant_deploy_hash) {
            return null;
        }

        return [
            'id' => $row->id,
            'control_hash' => $row->control_deploy_hash,
            'variant_hash' => $row->variant_deploy_hash,
            'control_headline' => $row->control_headline ?? '',
            'variant_headline' => $row->variant_headline ?? '',
        ];
    }
}
