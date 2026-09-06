<?php

declare(strict_types=1);

use App\Modules\X205\Ui\AffiliatePortal;
use App\Modules\X205\Ui\EarningsView;
use App\Modules\X205\Ui\PayoutRunView;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-205')->group(function () {
    Route::get('/portal', AffiliatePortal::class)->name('x-205.portal');
    Route::get('/earnings', EarningsView::class)->name('x-205.earnings');
    Route::get('/payout-run', PayoutRunView::class)->name('x-205.payout-run');
});
