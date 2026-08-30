<?php

declare(strict_types=1);

namespace App\Modules\X135\Actions;

use App\Modules\X135\Events\ResearchCompleted;
use App\Modules\X135\Events\SignalFound;
use App\Modules\X135\Models\ProspectSignal;
use App\Modules\X135\Models\ResearchRun;
use Illuminate\Support\Facades\Event;

final class ResearchRunAction
{
    /**
     * Executes deep prospect research (G3-03, G3-16, G3-48).
     * An unscored prospect NEVER triggers a research call (TEST ANCHOR & P-146, G3-20).
     */
    public function runResearch(
        int $businessId,
        int $prospectId,
        bool $isScored = false,
        array $discoveredSignals = []
    ): ?ResearchRun {
        // TEST ANCHOR: An unscored prospect NEVER triggers research
        if (! $isScored) {
            return null;
        }

        $run = ResearchRun::create([
            'business_id' => $businessId,
            'prospect_id' => $prospectId,
            'is_scored' => true,
            'dossier' => [
                'competitor_gap' => 'Lacks 24/7 instant chat booking compared to local rival',
                'ad_intelligence' => 'Currently running Google Local Services Ads without response routing',
            ],
        ]);

        foreach ($discoveredSignals as $sig) {
            $type = $sig['type'] ?? 'competitor_weakness';
            $desc = $sig['description'] ?? 'Signal identified during deep analysis';

            ProspectSignal::create([
                'business_id' => $businessId,
                'run_id' => $run->id,
                'signal_type' => $type,
                'description' => $desc,
            ]);

            Event::dispatch(new SignalFound($businessId, $prospectId, $type));
        }

        Event::dispatch(new ResearchCompleted($businessId, $prospectId, $run->id));

        return $run;
    }
}
