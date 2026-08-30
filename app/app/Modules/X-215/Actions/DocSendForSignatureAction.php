<?php

declare(strict_types=1);

namespace App\Modules\X215\Actions;

use App\Modules\X215\Events\DocSent;
use App\Modules\X215\Models\SignableDocument;
use App\Modules\X215\Models\SignatureRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class DocSendForSignatureAction
{
    /**
     * Sends document for signature with SHA-256 bound hash (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        string $title,
        string $contentBody,
        string $signerEmail,
        string $signerName
    ): array {
        $contentHash = hash('sha256', $contentBody);

        $doc = SignableDocument::create([
            'business_id' => $businessId,
            'document_uuid' => (string) Str::uuid(),
            'title' => $title,
            'content_body' => $contentBody,
            'content_hash' => $contentHash,
            'version' => 1,
            'status' => 'sent',
        ]);

        $request = SignatureRequest::create([
            'business_id' => $businessId,
            'document_id' => $doc->id,
            'signer_email' => $signerEmail,
            'signer_name' => $signerName,
            'bound_hash' => $contentHash,
            'status' => 'pending',
        ]);

        Event::dispatch(new DocSent($businessId, $doc->id, $signerEmail));

        return [
            'document' => $doc,
            'signature_request' => $request,
            'bound_hash' => $contentHash,
        ];
    }
}
