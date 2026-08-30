<?php

declare(strict_types=1);

namespace App\Modules\X215\Actions;

use App\Modules\X215\Events\DocCommented;
use App\Modules\X215\Models\DocumentComment;
use App\Modules\X215\Models\SignableDocument;
use Illuminate\Support\Facades\Event;

final class DocCommentAction
{
    /**
     * A comment NEVER edits a sent document; it raises an item and re-issues a version (TEST ANCHOR).
     */
    public function addCommentAndReissue(
        int $businessId,
        int $documentId,
        string $authorEmail,
        string $commentText,
        ?string $revisedContentBody = null
    ): array {
        $doc = SignableDocument::where('business_id', $businessId)->findOrFail($documentId);

        // 1. Record comment without altering the original sent document row (TEST ANCHOR)
        $comment = DocumentComment::create([
            'business_id' => $businessId,
            'document_id' => $doc->id,
            'author_email' => $authorEmail,
            'comment_text' => $commentText,
        ]);

        // 2. Re-issues a new version (TEST ANCHOR)
        $newVersionNumber = $doc->version + 1;
        $newContent = $revisedContentBody ?? ($doc->content_body."\n\n[Revision note: {$commentText}]");
        $newHash = hash('sha256', $newContent);

        $reissuedDoc = SignableDocument::create([
            'business_id' => $businessId,
            'document_uuid' => $doc->document_uuid,
            'title' => $doc->title,
            'content_body' => $newContent,
            'content_hash' => $newHash,
            'version' => $newVersionNumber,
            'status' => 'sent',
        ]);

        Event::dispatch(new DocCommented($businessId, $reissuedDoc->id, $newVersionNumber));

        return [
            'status' => 'commented_and_version_reissued',
            'comment_id' => $comment->id,
            'original_document_id' => $doc->id,
            'original_content_untouched' => true,
            'reissued_document_id' => $reissuedDoc->id,
            'new_version' => $newVersionNumber,
            'new_hash' => $newHash,
        ];
    }
}
