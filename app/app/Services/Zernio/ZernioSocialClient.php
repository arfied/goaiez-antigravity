<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Exceptions\GbpRequestFailed;
use InvalidArgumentException;

final class ZernioSocialClient
{
    private const array OUTCOME_FOR_STATUS = [
        'published' => 'published',
        'partial' => 'partial',
        'failed' => 'failed',
        'scheduled' => 'publishing',
        'publishing' => 'publishing',
        'draft' => 'unconfirmed',
        'cancelled' => 'failed',
    ];

    public function __construct(private readonly ZernioHttp $http) {}

    public function publish(array $targets, string $content, array $media, string $idempotencyKey, array $metadata = []): SocialPostReceipt
    {
        foreach ($targets as $target) {
            if (! isset($target['platform']) || ! isset($target['accountId'])) {
                throw new InvalidArgumentException('Target must have platform and accountId');
            }
            if ($target['platform'] !== 'facebook' && $target['platform'] !== 'instagram') {
                throw new InvalidArgumentException('Platform must be facebook or instagram');
            }
        }

        $this->http->assertUsable('social.zernio_enabled');

        $body = [
            'content' => $content,
            'platforms' => $targets,
            'publishNow' => true,
            'metadata' => (object) $metadata,
        ];
        if ($media !== []) {
            $body['mediaItems'] = $media;
        }

        try {
            $response = $this->http->post('posts', $body, $idempotencyKey);
        } catch (GbpRequestFailed $e) {
            if ($e->reason === 'connection_failed') {
                return new SocialPostReceipt('unconfirmed', null, null, [], null);
            }
            throw $e;
        }

        $status = $response->status();

        if ($status >= 200 && $status < 300) {
            $data = $response->json() ?? [];
            $post = $data['post'] ?? [];

            return $this->receiptFrom($post);
        }

        if ($status === 409) {
            $data = $response->json() ?? [];
            $existingPostId = $data['details']['existingPostId'] ?? null;
            if ($existingPostId !== null && $existingPostId !== '') {
                return new SocialPostReceipt('duplicate', null, null, [], $existingPostId);
            }
            $code = $data['code'] ?? null;
            $type = $data['type'] ?? null;
            if ($code === 'idempotency_conflict' || $type === 'idempotency_conflict') {
                return new SocialPostReceipt('in_flight', null, null, [], null);
            }
        }

        if ($status === 403 || $status === 429) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        if ($status >= 500) {
            return new SocialPostReceipt('unconfirmed', null, null, [], null);
        }

        throw GbpRequestFailed::from($response, accountScoped: true);
    }

    public function facebookReviews(string $accountRef, ?string $cursor = null, int $limit = 25): FacebookReviewPage
    {
        $this->http->assertUsable('social.zernio_enabled');

        $response = $this->http->get('/inbox/reviews', array_filter([
            'accountId' => $accountRef,
            'platform' => 'facebook',
            'limit' => max(1, min($limit, 50)),
            'cursor' => $cursor,
            'sortBy' => 'date',
        ], static fn (mixed $v): bool => $v !== null));

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $payload = $response->json('data');

        if (! is_array($payload)) {
            $payload = $response->json('reviews');
        }

        $reviews = [];

        foreach (is_array($payload) ? $payload : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $review = FacebookReview::fromZernio($entry);

            if ($review !== null) {
                $reviews[] = $review;
            }
        }

        $nextCursor = $response->json('pagination.nextCursor') ?? $response->json('nextCursor');

        return new FacebookReviewPage(
            reviews: $reviews,
            nextCursor: is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null,
        );
    }

    public function getPost(string $postId): SocialPostReceipt
    {
        $this->http->assertUsable('social.zernio_enabled');
        $response = $this->http->get('posts/'.rawurlencode($postId));

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        return $this->receiptFrom($response->json('post') ?? []);
    }

    public function replyToFacebookReview(string $accountRef, string $reviewId, string $message, string $idempotencyKey): ?string
    {
        $this->http->assertUsable('social.zernio_enabled');

        $message = trim($message);
        if ($message === '') {
            throw GbpRequestFailed::unreadable('reply_comment_empty');
        }

        $response = $this->http->post('inbox/reviews/'.rawurlencode($reviewId).'/reply', [
            'accountId' => $accountRef,
            'message' => $message,
        ], $idempotencyKey);

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $id = $response->json('reply.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function replyToComment(string $accountRef, string $zernioPostId, string $commentId, string $message, string $idempotencyKey): ?string
    {
        $this->http->assertUsable('social.zernio_enabled');

        $message = trim($message);
        if ($message === '') {
            throw GbpRequestFailed::unreadable('reply_comment_empty');
        }

        $response = $this->http->post('inbox/comments/'.rawurlencode($zernioPostId), [
            'accountId' => $accountRef,
            'message' => $message,
            'commentId' => $commentId,
        ], $idempotencyKey);

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $id = $response->json('data.commentId');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function receiptFrom(array $post): SocialPostReceipt
    {
        $vendorStatus = $post['status'] ?? '';
        $outcome = self::OUTCOME_FOR_STATUS[$vendorStatus] ?? 'unconfirmed';

        $platforms = [];
        foreach ($post['platforms'] ?? [] as $p) {
            $platformName = $p['platform'] ?? 'unknown';
            $platforms[$platformName] = new SocialPlatformResult(
                platform: $platformName,
                status: $p['status'] ?? '',
                platformPostId: $p['platformPostId'] ?? null,
                platformPostUrl: $p['platformPostUrl'] ?? null,
                errorMessage: $p['errorMessage'] ?? null,
                errorCategory: $p['errorCategory'] ?? null,
            );
        }

        return new SocialPostReceipt(
            outcome: $outcome,
            providerPostId: $post['_id'] ?? null,
            vendorStatus: $vendorStatus === '' ? null : $vendorStatus,
            platforms: $platforms,
            existingPostId: null,
        );
    }
}
