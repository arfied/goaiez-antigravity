<?php

declare(strict_types=1);

namespace App\Modules\X194\Actions;

use App\Modules\X194\Models\SavedView;

class SetDefaultViewAction
{
    public function setDefault(int $businessId, int $viewId): void
    {
        SavedView::where('business_id', $businessId)->update(['is_default' => false]);
        SavedView::where('business_id', $businessId)->where('id', $viewId)->update(['is_default' => true]);
    }
}
