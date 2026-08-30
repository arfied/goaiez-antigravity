<?php

declare(strict_types=1);

namespace App\Modules\X160\Actions;

use App\Modules\X160\Models\Document;

final class DocumentConfirmAction
{
    public function handle(int $businessId, int $documentId): array
    {
        $doc = Document::where('business_id', $businessId)->findOrFail($documentId);
        $doc->update(['status' => 'confirmed']);

        return [
            'status' => 'confirmed',
            'document_id' => $doc->id,
        ];
    }
}
