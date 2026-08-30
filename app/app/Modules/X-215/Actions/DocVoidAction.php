<?php

declare(strict_types=1);

namespace App\Modules\X215\Actions;

use App\Modules\X215\Events\DocVoided;
use App\Modules\X215\Models\SignableDocument;
use App\Modules\X215\Models\SignatureRequest;
use Illuminate\Support\Facades\Event;

final class DocVoidAction
{
    public function handle(int $businessId, int $documentId): array
    {
        $doc = SignableDocument::where('business_id', $businessId)->findOrFail($documentId);
        $doc->update(['status' => 'voided']);

        SignatureRequest::where('business_id', $businessId)
            ->where('document_id', $doc->id)
            ->update(['status' => 'voided']);

        Event::dispatch(new DocVoided($businessId, $doc->id));

        return [
            'status' => 'voided',
            'document_id' => $doc->id,
        ];
    }
}
