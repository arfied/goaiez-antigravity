<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-sms')->group(function () {
    Route::get('/thread', \App\Modules\CSms\Ui\Thread::class)->name('c-sms.thread');
    Route::get('/composer-segment-warning', \App\Modules\CSms\Ui\ComposerSegmentWarning::class)->name('c-sms.composer-segment-warning');
    Route::get('/donottext-list', \App\Modules\CSms\Ui\DonottextList::class)->name('c-sms.donottext-list');
    Route::get('/pernumber-complaint-monitoring', \App\Modules\CSms\Ui\PernumberComplaintMonitoring::class)->name('c-sms.pernumber-complaint-monitoring');
});

