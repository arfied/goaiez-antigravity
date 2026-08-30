<?php

declare(strict_types=1);

namespace App\Modules\X178\Actions;

use App\Modules\X178\Events\DesignChanged;
use App\Modules\X178\Models\DesignChange;
use Illuminate\Support\Facades\Event;

final class DesignChangeAction
{
    public function handle(
        int $businessId,
        int $pageId,
        string $changeType,
        string $blockRef,
        array $newState,
        ?array $prevState = null,
        float $contrastRatio = 7.0
    ): array {
        // 1. Contrast ratio check: a change failing 4.5:1 contrast cannot publish (TEST ANCHOR)
        if ($contrastRatio < 4.5) {
            return [
                'status' => 'refused',
                'refusal_code' => 'WCAG_CONTRAST_FAILED',
                'contrast_ratio' => $contrastRatio,
                'message' => 'Design change failed minimum 4.5:1 WCAG AA contrast threshold',
            ];
        }

        $change = DesignChange::create([
            'business_id' => $businessId,
            'page_id' => $pageId,
            'change_type' => $changeType,
            'block_ref' => $blockRef,
            'previous_state' => $prevState,
            'new_state' => $newState,
            'contrast_ratio' => $contrastRatio,
            'status' => 'applied',
        ]);

        Event::dispatch(new DesignChanged(
            businessId: $businessId,
            changeId: $change->id,
            blockRef: $blockRef,
            contrastRatio: $contrastRatio
        ));

        return [
            'status' => 'applied',
            'change_id' => $change->id,
            'block_ref' => $blockRef,
            'contrast_ratio' => $contrastRatio,
        ];
    }
}
