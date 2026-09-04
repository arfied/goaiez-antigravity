<?php

declare(strict_types=1);

use App\Modules\X188\Ui\ParkList;
use App\Modules\X188\Ui\PernumberComplaintBoard;
use App\Modules\X188\Ui\PoolInventory;
use App\Modules\X188\Ui\YourNumberCard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-188')->group(function () {
    Route::get('/your-number-card', YourNumberCard::class)->name('x-188.your-number-card');
    Route::get('/pool-inventory', PoolInventory::class)->name('x-188.pool-inventory');
    Route::get('/park-list', ParkList::class)->name('x-188.park-list');
    Route::get('/pernumber-complaint-board', PernumberComplaintBoard::class)->name('x-188.pernumber-complaint-board');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-188')->group(function () {
    Route::get('/your-number-card', YourNumberCard::class)->name('x-188.your-number-card.admin');
    Route::get('/pool-inventory', PoolInventory::class)->name('x-188.pool-inventory.admin');
    Route::get('/park-list', ParkList::class)->name('x-188.park-list.admin');
    Route::get('/pernumber-complaint-board', PernumberComplaintBoard::class)->name('x-188.pernumber-complaint-board.admin');
});
