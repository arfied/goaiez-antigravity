<?php

declare(strict_types=1);

namespace App\Modules\X186\Domain;

final class X186Engine
{
    public function validateMarketingClass(string $messageClass): void
    {
        if (strtolower($messageClass) !== 'marketing') {
            throw new \DomainException('REFUSES: every send from X-186 must declare Marketing class [G11-24]');
        }
    }

    public function validateSendTime(int $hourOfDay): void
    {
        // marketing window example 8 to 20
        if ($hourOfDay < 8 || $hourOfDay >= 20) {
            throw new \DomainException('REFUSES: send time outside the marketing window [G2-14, G11-31]');
        }
    }

    public function validateScrubbing(bool $isScrubbed): void
    {
        if (! $isScrubbed) {
            throw new \DomainException('REFUSES: to send without scrubbing against a fresh suppression list [G10-25]');
        }
    }

    public function validateContinuation(bool $hasWonDeal): void
    {
        if ($hasWonDeal) {
            throw new \DomainException('REFUSES: to continue the sequence after a won deal [G12-28]');
        }
    }

    public function processBody(string $body): string
    {
        return $body;
    }
}
