<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-188')->group(function () {
    Route::get('/your-number-card', \App\Modules\X188\Ui\YourNumberCard::class)->name('x-188.your-number-card');
    Route::get('/pool-inventory', \App\Modules\X188\Ui\PoolInventory::class)->name('x-188.pool-inventory');
    Route::get('/park-list', \App\Modules\X188\Ui\ParkList::class)->name('x-188.park-list');
    Route::get('/pernumber-complaint-board', \App\Modules\X188\Ui\PernumberComplaintBoard::class)->name('x-188.pernumber-complaint-board');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-188')->group(function () {
    Route::get('/your-number-card', \App\Modules\X188\Ui\YourNumberCard::class)->name('x-188.your-number-card.admin');
    Route::get('/pool-inventory', \App\Modules\X188\Ui\PoolInventory::class)->name('x-188.pool-inventory.admin');
    Route::get('/park-list', \App\Modules\X188\Ui\ParkList::class)->name('x-188.park-list.admin');
    Route::get('/pernumber-complaint-board', \App\Modules\X188\Ui\PernumberComplaintBoard::class)->name('x-188.pernumber-complaint-board.admin');
});

