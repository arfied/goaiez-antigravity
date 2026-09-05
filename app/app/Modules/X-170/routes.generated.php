<?php

declare(strict_types=1);

use App\Modules\X170\Ui\Commissions;
use App\Modules\X170\Ui\ScorecardUi;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-170')->group(function () {
    Route::get('/commissions', Commissions::class)->name('x-170.commissions');
    Route::get('/scorecard', ScorecardUi::class)->name('x-170.scorecard');
});
