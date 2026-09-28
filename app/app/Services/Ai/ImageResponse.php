<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiModel;
use App\Services\Config\DefaultsRegistry;

final readonly class ImageResponse
{
    public function __construct(
        public ?string $bytes,
        public AiModel $model,
        public int $inputTokens,
        public int $outputTokens,
        public ?string $failureReason,
        public bool $usageReported,
    ) {}

    public static function failed(AiModel $model, string $reason): self
    {
        return new self(
            bytes: null,
            model: $model,
            inputTokens: 0,
            outputTokens: 0,
            failureReason: $reason,
            usageReported: false,
        );
    }

    public function isUsable(): bool
    {
        return $this->failureReason === null && $this->bytes !== null;
    }

    public function costInHundredthsOfCents(): int
    {
        if (! $this->usageReported) {
            return app(DefaultsRegistry::class)->int('ai.image.fallback_cost_hundredths');
        }

        return $this->model->costOf($this->inputTokens, $this->outputTokens);
    }
}
