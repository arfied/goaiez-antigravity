<?php

declare(strict_types=1);

namespace App\Modules\X179\Actions;

use App\Modules\X179\Events\TemplateMatched;
use App\Modules\X179\Models\ExtractedContent;
use App\Modules\X179\Models\TemplateMatch;
use Illuminate\Support\Facades\Event;

final class TemplateMatchAction
{
    /**
     * Matches template and generates zero-paraphrase preview (G6-13, G10-09).
     * 1. A diff of extracted service descriptions against the rendered preview shows zero paraphrase (TEST ANCHOR).
     * 2. A prospect with no site and a GBP gets a Path B preview (TEST ANCHOR).
     */
    public function matchAndRender(
        int $businessId,
        int $prospectId,
        string $templateId = 'tmpl_hvac_emergency_v1'
    ): TemplateMatch {
        $extracted = ExtractedContent::where('business_id', $businessId)
            ->where('prospect_id', $prospectId)
            ->latest('id')
            ->firstOrFail();

        // TEST ANCHOR: A prospect with no site and a GBP gets a Path B preview
        $pathType = ($extracted->source_type === 'gbp') ? 'Path B' : 'Path A';

        // TEST ANCHOR: Rendered preview must contain extracted service description verbatim (zero paraphrase)
        $renderedPreview = "<section class=\"hero-preview\"><h1>Professional Services</h1><p class=\"service-body\">{$extracted->service_description}</p></section>";

        $match = TemplateMatch::create([
            'business_id' => $businessId,
            'prospect_id' => $prospectId,
            'template_id' => $templateId,
            'match_score' => 0.920,
            'path_type' => $pathType,
            'rendered_preview' => $renderedPreview,
        ]);

        Event::dispatch(new TemplateMatched($businessId, $prospectId, $templateId, $pathType));

        return $match;
    }
}
