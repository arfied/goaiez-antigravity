<?php

declare(strict_types=1);

namespace App\Modules\X183\Actions;

use App\Modules\X183\Models\ContentDraft;

final class ContentWriteAction
{
    /**
     * Writes content draft or case study (G8-39, G9-17, G16-03).
     */
    public function writeDraft(
        int $businessId,
        string $title,
        string $bodyText,
        bool $isCaseStudy = false,
        bool $hasDoubleConsent = false
    ): ContentDraft {
        return ContentDraft::create([
            'business_id' => $businessId,
            'title' => $title,
            'body_text' => $bodyText,
            'is_case_study' => $isCaseStudy,
            'has_double_consent' => $hasDoubleConsent,
            'is_approved' => false,
            'is_published' => false,
        ]);
    }
}
