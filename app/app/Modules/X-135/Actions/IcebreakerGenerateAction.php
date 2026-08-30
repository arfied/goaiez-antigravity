<?php

declare(strict_types=1);

namespace App\Modules\X135\Actions;

use App\Modules\X135\Events\IcebreakerGenerated;
use App\Modules\X135\Models\Icebreaker;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class IcebreakerGenerateAction
{
    /**
     * Generates a fact-grounded icebreaker.
     * Every icebreaker row carries a source_url and date (TEST ANCHOR & G5-26, G3-59).
     */
    public function generateIcebreaker(
        int $businessId,
        int $runId,
        int $prospectId,
        string $openerText,
        string $sourceUrl,
        ?string $observedDate = null
    ): Icebreaker {
        if (empty($sourceUrl)) {
            throw new InvalidArgumentException('Icebreaker generation failed: every icebreaker must carry a valid source_url (TEST ANCHOR)');
        }

        $date = $observedDate ?? now()->toDateString();

        $icebreaker = Icebreaker::create([
            'business_id' => $businessId,
            'run_id' => $runId,
            'prospect_id' => $prospectId,
            'opener_text' => $openerText,
            'source_url' => $sourceUrl,
            'observed_date' => $date,
        ]);

        Event::dispatch(new IcebreakerGenerated($businessId, $prospectId, $openerText, $sourceUrl));

        return $icebreaker;
    }
}
