<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;

class CWhatsappFiller implements DemoFiller
{
    public function module(): string
    {
        return 'C-Whatsapp';
    }

    public function fill(Business $business): int
    {
        if (WhatsappTemplate::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        WhatsappTemplate::create([
            'business_id' => $business->id,
            'name' => 'demo·appointment_reminder',
            'body_text' => 'Hi {{1}}, just confirming your visit tomorrow.',
            'status' => 'approved',
        ]);

        WhatsappTemplate::create([
            'business_id' => $business->id,
            'name' => 'demo·quote_followup',
            'body_text' => 'Hi {{1}}, did you have any questions about the quote?',
            'status' => 'pending_approval',
        ]);

        WhatsappTemplate::create([
            'business_id' => $business->id,
            'name' => 'demo·review_request',
            'body_text' => 'Thanks for having us out — would you leave a quick review?',
            'status' => 'pending_approval',
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        return WhatsappTemplate::where('business_id', $business->id)
            ->where('name', 'like', self::MARKER.'%')
            ->delete();
    }
}
