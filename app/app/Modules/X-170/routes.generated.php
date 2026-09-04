<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-170')->group(function () {
    Route::get('/commissions', \App\Modules\X170\Ui\Commissions::class)->name('x-170.commissions');
    Route::get('/scorecard', \App\Modules\X170\Ui\ScorecardUi::class)->name('x-170.scorecard');
});

