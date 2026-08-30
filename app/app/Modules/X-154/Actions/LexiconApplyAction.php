<?php

declare(strict_types=1);

namespace App\Modules\X154\Actions;

use App\Modules\X154\Models\TenantLexicon;

final class LexiconApplyAction
{
    private const MEDICAL_FORBIDDEN_TERMS = ['diagnosis', 'prescription', 'patient', 'clinical', 'medical'];

    /**
     * Applies tenant lexicon to compose string.
     * 1. A service the tenant calls "drain clear" is NEVER rendered as "drain cleaning" on any channel (TEST ANCHOR).
     * 2. R19 keeps medical terms out (G2-40).
     * 3. Compose-time only, no LLM in send path (P-071, G5-47).
     */
    public function applyLexicon(int $businessId, string $templateText): string
    {
        // R19: Filter/strip medical terminology (G2-40)
        foreach (self::MEDICAL_FORBIDDEN_TERMS as $medTerm) {
            $templateText = preg_replace('/\b'.preg_quote($medTerm, '/').'\b/i', 'service', $templateText);
        }

        $mappings = TenantLexicon::where('business_id', $businessId)
            ->where('is_confirmed', true)
            ->get();

        foreach ($mappings as $mapping) {
            // Case-insensitive word boundary replacement with tenant's exact preferred wording (TEST ANCHOR)
            $templateText = preg_replace(
                '/\b'.preg_quote($mapping->generic_term, '/').'\b/i',
                $mapping->preferred_term,
                $templateText
            );
        }

        return $templateText;
    }
}
