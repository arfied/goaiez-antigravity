<?php

declare(strict_types=1);

namespace App\Modules\X208\Domain;

final class X208Engine
{
    public function validateLobKey(string $keySource): array
    {
        if ($keySource !== 'tenant') {
            return ['status' => 'refused', 'reason' => 'platform account not allowed'];
        }

        return ['status' => 'ok'];
    }

    public function generateMail(bool $isDoNotMail): array
    {
        if ($isDoNotMail) {
            return ['status' => 'refused', 'reason' => 'Do-Not-Mail checked at generation'];
        }

        return ['status' => 'generated'];
    }

    public function recallMail(): array
    {
        return ['status' => 'refused', 'reason' => 'physical mail cannot be recalled'];
    }

    public function validatePromotion(bool $hasOffer, ?string $promotionId): array
    {
        if ($hasOffer && empty($promotionId)) {
            return ['status' => 'refused', 'reason' => 'missing promotion_id'];
        }

        return ['status' => 'ok'];
    }

    public function approveMail(bool $costShown): array
    {
        if (! $costShown) {
            return ['status' => 'refused', 'reason' => 'cost must be shown before approval'];
        }

        return ['status' => 'approved'];
    }
}
