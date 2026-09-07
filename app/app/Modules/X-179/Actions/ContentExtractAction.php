<?php

declare(strict_types=1);

namespace App\Modules\X179\Actions;

use App\Modules\X179\Events\ContentExtracted;
use App\Modules\X179\Models\ExtractedContent;
use Illuminate\Support\Facades\Event;

final class ContentExtractAction
{
    /**
     * Extracts content from site or GBP with tech stack detection (G11-07).
     */
    public function extractContent(
        int $businessId,
        int $prospectId,
        string $sourceType,
        string $serviceDescription,
        ?string $techStack = null
    ): ExtractedContent {
        $engine = new \App\Modules\X179\Domain\TemplateEngine();
        $excludedDescription = $engine->excludeBoilerplate($serviceDescription);

        $content = ExtractedContent::create([
            'business_id' => $businessId,
            'prospect_id' => $prospectId,
            'source_type' => $sourceType,
            'service_description' => $excludedDescription, // Verbatim source (TEST ANCHOR)
            'tech_stack' => $techStack,
        ]);

        Event::dispatch(new ContentExtracted($businessId, $prospectId, $sourceType));

        return $content;
    }
}
