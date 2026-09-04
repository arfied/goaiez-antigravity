<?php

declare(strict_types=1);

namespace App\Modules\X194\Actions;

use App\Modules\X194\Models\SavedView;
use Illuminate\Support\LazyCollection;

class ViewListAction
{
    /**
     * @return LazyCollection<int, SavedView>
     */
    public function listViews(int $businessId): LazyCollection
    {
        return SavedView::where('business_id', $businessId)->lazy();
    }
}
