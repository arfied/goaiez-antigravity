<?php

declare(strict_types=1);

namespace App\Modules\X181\Actions;

use App\Modules\X181\Models\QaTicket;

final class QaMarketingSuppressionCheckAction
{
    /**
     * An OPEN qa_ticket on a Person suppresses every GROW action toward them (TEST ANCHOR & P-205).
     */
    public function isGrowSuppressed(int $businessId, int $personId): bool
    {
        return QaTicket::where('business_id', $businessId)
            ->where('person_id', $personId)
            ->where('status', 'open')
            ->exists();
    }
}
