<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Domain;

final class TemplateStatuses
{
    public const array FROM_ZERNIO = [
        'PENDING' => 'pending_approval',
        'APPROVED' => 'approved',
        'REJECTED' => 'rejected',
        'IN_APPEAL' => 'in_appeal',
        'PAUSED' => 'paused',
        'DISABLED' => 'disabled',
        'PENDING_DELETION' => 'pending_deletion',
    ];

    public static function fromZernio(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }

        return self::FROM_ZERNIO[$s] ?? null;
    }
}
