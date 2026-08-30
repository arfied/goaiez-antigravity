<?php

declare(strict_types=1);

namespace App\Modules\X134\Actions;

use App\Modules\X134\Models\EnrichmentField;

final class EnrichExportAction
{
    /**
     * Exports fields for outreach template rendering.
     * Guaranteed: Low-confidence / unverified fields (< threshold) are excluded (TEST ANCHOR).
     */
    public function exportTemplateData(int $businessId, string $entityId): array
    {
        $fields = EnrichmentField::where('business_id', $businessId)
            ->where('entity_id', $entityId)
            ->where('is_usable', true) // Filter out low confidence fields
            ->get();

        $data = [];
        foreach ($fields as $field) {
            $data[$field->field_key] = $field->field_value;
        }

        return $data;
    }
}
