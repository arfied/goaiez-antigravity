<?php

declare(strict_types=1);

namespace App\Modules\X194\Actions;

use App\Modules\X194\Events\ViewSaved;
use App\Modules\X194\Models\SavedView;
use Illuminate\Support\Facades\Event;

final class ViewSaveAction
{
    /**
     * Saves a view configuration.
     * A saved view is ALWAYS a row in saved_views, NEVER a file on disk (TEST ANCHOR).
     */
    public function save(
        int $businessId,
        string $viewName,
        string $viewType = 'table',
        ?array $filterConfig = null,
        ?array $columnsConfig = null,
        bool $isDefault = false
    ): SavedView {
        $savedView = SavedView::create([
            'business_id' => $businessId,
            'view_name' => $viewName,
            'view_type' => $viewType,
            'filter_config' => $filterConfig,
            'columns_config' => $columnsConfig,
            'is_default' => $isDefault,
        ]);

        Event::dispatch(new ViewSaved($businessId, $savedView->id, $viewName));

        return $savedView;
    }
}
