<?php

declare(strict_types=1);

use App\Modules\X145\Ui\ProposalsAppearToday;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-145')->group(function () {
    Route::get('/proposals-appear-today', ProposalsAppearToday::class)->name('x-145.proposals-appear-today');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-145')->group(function () {
    Route::get('/proposals-appear-today', ProposalsAppearToday::class)->name('x-145.proposals-appear-today.admin');
});
