<?php

declare(strict_types=1);

namespace App\Modules\X120\Actions;

use App\Modules\X120\Models\CardToken;

final class CardRotateAction
{
    public function rotateDefault(int $businessId, int $newDefaultCardId): CardToken
    {
        CardToken::where('business_id', $businessId)->update(['is_default' => false]);

        $token = CardToken::where('business_id', $businessId)->findOrFail($newDefaultCardId);
        $token->update(['is_default' => true]);

        return $token;
    }
}
