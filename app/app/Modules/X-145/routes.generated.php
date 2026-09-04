<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-145')->group(function () {
    Route::get('/proposals-appear-today', \App\Modules\X145\Ui\ProposalsAppearToday::class)->name('x-145.proposals-appear-today');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-145')->group(function () {
    Route::get('/proposals-appear-today', \App\Modules\X145\Ui\ProposalsAppearToday::class)->name('x-145.proposals-appear-today.admin');
});

