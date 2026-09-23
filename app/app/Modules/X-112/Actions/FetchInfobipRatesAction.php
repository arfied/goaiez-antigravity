<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

final class FetchInfobipRatesAction
{
    /**
     * [G7-31]
     */
    public function getRates(): array
    {
        // R245: Fetch Infobip rates from X-82 config
        return config('x82.infobip_rates', [
            'sms_segment' => ['cost' => 10, 'margin' => 2],
        ]);
    }
}
