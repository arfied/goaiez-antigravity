<?php

declare(strict_types=1);

namespace App\Modules\X182\Actions;

use App\Modules\X182\Events\CommentEscalated;
use App\Modules\X182\Events\CommentReceived;
use App\Modules\X182\Models\Comment;
use App\Modules\X182\Models\SocialPost;
use Illuminate\Support\Facades\Event;

final class CommentReplyAction
{
    /**
     * Processes inbound social comments (G12-12, G12-17).
     * TEST ANCHOR: A comment classified negative NEVER receives an automated public reply — the only write is an inbox row.
     */
    public function handleInboundComment(
        int $businessId,
        int $postId,
        string $authorName,
        string $commentText,
        string $sentiment = 'positive'
    ): Comment {
        $post = SocialPost::where('business_id', $businessId)->findOrFail($postId);

        $isNegative = ($sentiment === 'negative');

        // TEST ANCHOR: negative sentiment NEVER receives automated public reply
        $isPubliclyReplied = (! $isNegative);
        $replyText = $isPubliclyReplied ? "Thank you {$authorName}! We appreciate your feedback." : null;
        $isEscalatedToInbox = $isNegative;

        $comment = Comment::create([
            'business_id' => $businessId,
            'post_id' => $post->id,
            'author_name' => $authorName,
            'comment_text' => $commentText,
            'sentiment' => $sentiment,
            'is_publicly_replied' => $isPubliclyReplied,
            'automated_reply_text' => $replyText,
            'is_escalated_to_inbox' => $isEscalatedToInbox,
        ]);

        Event::dispatch(new CommentReceived($businessId, $comment->id, $sentiment));

        if ($isNegative) {
            // Escalate negative comment exclusively to human inbox without public reply
            Event::dispatch(new CommentEscalated($businessId, $comment->id, 'Negative sentiment detected: public reply suppressed'));
        }

        return $comment;
    }
}
