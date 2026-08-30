<?php

declare(strict_types=1);

namespace App\Modules\X178\Actions;

use App\Modules\X178\Events\BlockAdded;
use App\Modules\X178\Models\DesignChange;
use Illuminate\Support\Facades\Event;

final class FormGenerateAction
{
    public function handle(int $businessId, int $pageId, string $niche = 'unmapped'): array
    {
        // G6-21: for an unmapped niche, never invents a price — [fill-me] only (P-092)
        $formConfig = [
            'fields' => ['full_name', 'phone', 'service_address'],
            'pricing_display' => '[fill-me]',
            'niche' => $niche,
        ];

        $blockRef = 'block_form_lead_capture_'.uniqid();

        $change = DesignChange::create([
            'business_id' => $businessId,
            'page_id' => $pageId,
            'change_type' => 'form_gen',
            'block_ref' => $blockRef, // block reference only, no generated markup (TEST ANCHOR)
            'new_state' => $formConfig,
            'contrast_ratio' => 8.5,
            'status' => 'applied',
        ]);

        Event::dispatch(new BlockAdded($businessId, $pageId, $blockRef));

        return [
            'status' => 'generated',
            'block_ref' => $blockRef,
            'form_config' => $formConfig,
        ];
    }
}
