<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X188\Ui\ParkList;
use App\Modules\X188\Ui\PernumberComplaintBoard;
use App\Modules\X188\Ui\PoolInventory;
use App\Modules\X188\Ui\YourNumberCard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-188')->group(function () {
    Route::get('/x-188/your-number-card', YourNumberCard::class)->name('x-188.your-number-card');
    Route::get('/x-188/pool-inventory', PoolInventory::class)->name('x-188.pool-inventory');
    Route::get('/x-188/park-list', ParkList::class)->name('x-188.park-list');
    Route::get('/x-188/pernumber-complaint-board', PernumberComplaintBoard::class)->name('x-188.pernumber-complaint-board');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-188')->group(function () {
    Route::get('/x-188/your-number-card', YourNumberCard::class)->name('x-188.your-number-card');
    Route::get('/x-188/pool-inventory', PoolInventory::class)->name('x-188.pool-inventory');
    Route::get('/x-188/park-list', ParkList::class)->name('x-188.park-list');
    Route::get('/x-188/pernumber-complaint-board', PernumberComplaintBoard::class)->name('x-188.pernumber-complaint-board');
});
