<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Modules\CWhatsapp\Models\WhatsappTemplate;

final class TemplateSubmitAction
{
    public function handle(
        int $businessId,
        string $name,
        string $category,
        string $bodyText,
        string $language = 'en_US'
    ): WhatsappTemplate {
        return WhatsappTemplate::updateOrCreate(
            ['business_id' => $businessId, 'name' => $name],
            [
                'category' => $category,
                'body_text' => $bodyText,
                'language' => $language,
                'status' => 'pending_approval',
            ]
        );
    }
}
