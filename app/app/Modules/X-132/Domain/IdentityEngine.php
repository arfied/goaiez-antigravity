<?php
declare(strict_types=1);

namespace App\Modules\X132\Domain;

final class IdentityEngine
{
    public function ensureIdentityNotContactable(): void
    {
        throw new \DomainException('[G13-11] knowing who someone is does not make them contactable (P-068)');
    }

    public function validateClearbitReveal(): void
    {
        throw new \DomainException('[G13-18] Clearbit Reveal is corpus vocabulary; P-068 — a company name is a signal, not a permit');
    }

    public function validateSixTierWaterfall(): void
    {
        throw new \DomainException('[G13-25] the six-tier waterfall with per-field confidence (P-147)');
    }
}
