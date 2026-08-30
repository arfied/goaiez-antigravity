<?php

declare(strict_types=1);

namespace App\Modules\X138\Actions;

use App\Modules\X138\Events\AttributionAmbiguous;
use App\Modules\X138\Events\JobAttributed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class AttributionQueryAction
{
    /**
     * Executes multi-touch query over recorded journey touches (G9-13, G13-06, G13-16).
     * A job with two qualifying touches renders both and writes attribution.ambiguous (TEST ANCHOR).
     * The estimate tile is dashed until job_value is non-null (TEST ANCHOR).
     */
    public function queryJobAttribution(
        int $businessId,
        int $jobId,
        array $qualifyingTouches,
        ?int $jobValueCents = null
    ): array {
        $touchCount = count($qualifyingTouches);
        $status = ($touchCount >= 2) ? 'ambiguous' : (($touchCount === 1) ? 'single' : 'none');

        $now = Carbon::now();

        $queryId = DB::table('attribution_queries')->insertGetId([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'job_value' => $jobValueCents,
            'touches' => json_encode($qualifyingTouches),
            'attribution_status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($status === 'ambiguous') {
            Event::dispatch(new AttributionAmbiguous($businessId, $jobId, $qualifyingTouches));
        } elseif ($status === 'single') {
            $firstTouch = $qualifyingTouches[0]['source'] ?? 'direct';
            Event::dispatch(new JobAttributed($businessId, $jobId, $firstTouch));
        }

        $estimateTileDisplay = ($jobValueCents === null)
            ? '--' // Dashed until job_value is non-null (TEST ANCHOR)
            : '$'.number_format($jobValueCents / 100, 2);

        return [
            'query_id' => $queryId,
            'job_id' => $jobId,
            'attribution_status' => $status,
            'qualifying_touches' => $qualifyingTouches,
            'job_value' => $jobValueCents,
            'estimate_tile' => $estimateTileDisplay,
            'is_dashed' => ($jobValueCents === null),
        ];
    }
}
