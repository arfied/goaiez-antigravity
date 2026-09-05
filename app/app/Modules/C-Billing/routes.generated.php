<?php

declare(strict_types=1);

use App\Modules\CBilling\Ui\Credits;
use App\Modules\CBilling\Ui\DunningBoard;
use App\Modules\CBilling\Ui\Mrr;
use App\Modules\CBilling\Ui\RevenueRecovery;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-billing')->group(function () {
    Route::get('/credits', Credits::class)->name('c-billing.credits');
    Route::get('/mrr', Mrr::class)->name('c-billing.mrr');
    Route::get('/revenue-recovery', RevenueRecovery::class)->name('c-billing.revenue-recovery');
    Route::get('/dunning-board', DunningBoard::class)->name('c-billing.dunning-board');
});
