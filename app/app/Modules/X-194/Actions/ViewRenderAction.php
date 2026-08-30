<?php

declare(strict_types=1);

namespace App\Modules\X194\Actions;

use App\Modules\X194\Events\ViewRendered;
use App\Modules\X194\Models\SavedView;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class ViewRenderAction
{
    /**
     * Renders a view in the location's own timezone (G9-37).
     * Counts and estimates never blend; estimate tile stays dashed ("--") until value entered (G9-35).
     */
    public function renderView(
        int $businessId,
        int $viewId,
        string $locationTimezone = 'UTC',
        ?float $jobValue = null,
        int $jobCount = 0
    ): array {
        $view = SavedView::where('business_id', $businessId)->findOrFail($viewId);

        $renderedAt = Carbon::now($locationTimezone)->toIso8601String();

        // G9-35: counts and estimates never blend; estimate tile stays violet-dashed ("--") if job value is null
        $estimateTile = ($jobValue !== null)
            ? '$'.number_format($jobValue, 2)
            : '--';

        Event::dispatch(new ViewRendered($businessId, $view->id, $locationTimezone));

        return [
            'view_id' => $view->id,
            'view_name' => $view->view_name,
            'timezone' => $locationTimezone,
            'rendered_at' => $renderedAt,
            'job_count' => $jobCount,
            'estimate_tile' => $estimateTile,
        ];
    }
}
