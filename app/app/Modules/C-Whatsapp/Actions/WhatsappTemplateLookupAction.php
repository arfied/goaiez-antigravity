<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Collection;

final class WhatsappTemplateLookupAction
{
    /**
     * @return Collection<int, WhatsappTemplate>
     */
    public function sendable(int $businessId): Collection
    {
        return WhatsappTemplate::where('business_id', $businessId)
            ->where('status', 'approved')
            ->where('body_text', 'not like', '%{{%')
            ->orderBy('name')
            ->get();
    }
}
