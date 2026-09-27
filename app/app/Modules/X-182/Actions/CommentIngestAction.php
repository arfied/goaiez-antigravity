<?php

declare(strict_types=1);

namespace App\Modules\X182\Actions;

use App\Modules\X182\Models\Comment;
use App\Modules\X182\Models\SocialPost;
use App\Support\Tenancy;
use Carbon\Carbon;

final class CommentIngestAction
{
    /**
     * @param  array<string, mixed>  $comment
     */
    public function fromZernio(int $businessId, array $comment): string
    {
        $zernioPostId = $comment['postId'] ?? null;
        if (! is_string($zernioPostId) || $zernioPostId === '') {
            return 'unknown_post';
        }

        if (($comment['author']['isOwnAccount'] ?? false) === true) {
            return 'own_reply';
        }

        $post = SocialPost::where('business_id', $businessId)->where('provider_post_id', $zernioPostId)->first();
        if ($post === null) {
            return 'unknown_post';
        }

        $ref = $comment['id'] ?? null;
        if (! is_string($ref) || $ref === '') {
            return 'ignored';
        }

        if (Comment::where('platform_comment_id', $ref)->exists()) {
            return 'duplicate';
        }

        $authorName = 'Someone';
        if (isset($comment['author']['name']) && is_string($comment['author']['name'])) {
            $authorName = $comment['author']['name'];
        } elseif (isset($comment['author']['username']) && is_string($comment['author']['username'])) {
            $authorName = $comment['author']['username'];
        }

        $commentText = '';
        if (isset($comment['text']) && is_string($comment['text'])) {
            $commentText = $comment['text'];
        }

        $newComment = new Comment;
        $newComment->business_id = $businessId;
        $newComment->post_id = $post->id;
        $newComment->author_name = $authorName;
        $newComment->comment_text = $commentText;
        $newComment->sentiment = 'unclassified';
        $newComment->is_publicly_replied = false;
        $newComment->automated_reply_text = null;
        $newComment->is_escalated_to_inbox = false;
        $newComment->platform_comment_id = $ref;

        if (isset($comment['parentCommentId']) && is_string($comment['parentCommentId'])) {
            $newComment->parent_comment_id = $comment['parentCommentId'];
        }

        if (isset($comment['platform']) && is_string($comment['platform'])) {
            $newComment->platform = $comment['platform'];
        }

        if (isset($comment['author']['id']) && is_string($comment['author']['id'])) {
            $newComment->author_ref = $comment['author']['id'];
        }

        if (isset($comment['createdAt']) && is_string($comment['createdAt'])) {
            $newComment->commented_at = Carbon::parse($comment['createdAt']);
        }

        $newComment->save();

        return 'stored';
    }

    public function recordOwnerReply(Comment $comment, string $text, ?string $replyRef): void
    {
        if ((int) $comment->business_id !== Tenancy::idOrFail()) {
            throw new \InvalidArgumentException('Comment does not belong to the current tenant');
        }

        $comment->reply_text = $text;
        $comment->reply_ref = $replyRef;
        $comment->replied_at = Carbon::now();
        $comment->is_publicly_replied = true;
        $comment->save();
    }
}
