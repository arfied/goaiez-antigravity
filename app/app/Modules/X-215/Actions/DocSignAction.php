<?php

declare(strict_types=1);

namespace App\Modules\X215\Actions;

use App\Modules\X215\Events\DocSigned;
use App\Modules\X215\Models\SignableDocument;
use App\Modules\X215\Models\SignatureRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class DocSignAction
{
    /**
     * The signature binds a HASH of the rendered document — alter one character and it is invalid (TEST ANCHOR).
     */
    public function sign(
        int $businessId,
        int $requestId,
        string $signatureData,
        ?string $currentRenderedContent = null
    ): array {
        $request = SignatureRequest::where('business_id', $businessId)->findOrFail($requestId);
        $doc = SignableDocument::where('business_id', $businessId)->findOrFail($request->document_id);

        if ($request->status !== 'pending') {
            return [
                'status' => 'refused',
                'refusal_code' => 'SIGNATURE_REQUEST_NOT_PENDING',
                'message' => 'A signature request that is not pending cannot be signed',
                'signed' => false,
            ];
        }

        $contentToVerify = $currentRenderedContent ?? $doc->content_body;
        $currentHash = hash('sha256', $contentToVerify);

        // 1. Cryptographic hash binding verification (TEST ANCHOR)
        if ($currentHash !== $request->bound_hash) {
            return [
                'status' => 'refused',
                'refusal_code' => 'DOCUMENT_CONTENT_ALTERED_HASH_MISMATCH',
                'message' => 'The signature binds a hash of the rendered document — alter one character and it is invalid',
                'signed' => false,
            ];
        }

        $request->update([
            'signature_data' => $signatureData,
            'signed_at' => Carbon::now(),
            'status' => 'signed',
        ]);

        $doc->update(['status' => 'signed']);

        Event::dispatch(new DocSigned($businessId, $doc->id, $request->bound_hash));

        return [
            'status' => 'signed',
            'signed' => true,
            'document_id' => $doc->id,
            'bound_hash' => $request->bound_hash,
        ];
    }
}
