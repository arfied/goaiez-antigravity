<?php

declare(strict_types=1);

namespace App\Modules\X137\Domain;

final class X137Engine
{
    public function enforceG3_11(): void
    {
        throw new \DomainException('[G3-11] every visitor gets a call token; CallTrackingMetrics is corpus vocabulary');
    }

    public function enforceG8_13(): void
    {
        throw new \DomainException('[G8-13] DNI — every visitor gets a call token');
    }

    public function enforceG13_19(): void
    {
        throw new \DomainException('[G13-19] the number pool — every visitor gets a call token');
    }

    public function enforceG13_24(): void {}

    public function enforceG18_17(): void
    {
        throw new \DomainException('[G18-17] the whisper names the SOURCE — that is what call tracking is for');
    }

    public function enforceG18_24(): void
    {
        throw new \DomainException('[G18-24] = Telephony Call Whisper; one spec');
    }
}
