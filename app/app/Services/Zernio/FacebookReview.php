<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Services\Gbp\GbpReview;
use Illuminate\Support\Carbon;

final readonly class FacebookReview
{
    public function __construct(
        public string $externalId,
        public ?int $rating,
        public ?string $comment,
        public ?string $authorName,
        public ?Carbon $createdAt,
        public ?Carbon $updatedAt,
        public bool $hasOwnerReply,
        public ?bool $ownerReplyReported,
        public ?string $recommendation,
    ) {}

    public static function fromZernio(array $payload): ?self
    {
        $gbpReview = GbpReview::fromZernio($payload);

        if ($gbpReview === null) {
            return null;
        }

        $recommendationType = $payload['recommendationType'] ?? null;
        $recommendation = ($recommendationType === 'positive' || $recommendationType === 'negative')
            ? $recommendationType
            : null;

        return new self(
            externalId: $gbpReview->externalId,
            rating: $gbpReview->rating,
            comment: $gbpReview->comment,
            authorName: $gbpReview->authorName,
            createdAt: $gbpReview->createdAt,
            updatedAt: $gbpReview->updatedAt,
            hasOwnerReply: $gbpReview->hasOwnerReply,
            ownerReplyReported: $gbpReview->ownerReplyReported,
            recommendation: $recommendation,
        );
    }
}
