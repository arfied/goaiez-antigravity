<?php

declare(strict_types=1);

namespace App\Modules\X183\Domain;

final class GateEngine
{
    public function requireDoubleConsent(bool $consent1, bool $consent2): bool
    {
        return $consent1 && $consent2;
    }

    public function escalateNegativeComment(string $comment): string
    {
        return str_contains($comment, 'bad') ? 'ApprovalDesk' : 'None';
    }

    public function requireRealData(bool $isRealData): bool
    {
        return $isRealData;
    }

    public function prePublishGate(bool $hasGrounding, bool $hasCitation): bool
    {
        return $hasGrounding && $hasCitation;
    }

    public function noSamplePrices(string $content): bool
    {
        return ! str_contains($content, 'SAMPLE PRICE');
    }
}
