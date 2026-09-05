<?php

declare(strict_types=1);

use App\Modules\CSms\Ui\ComposerSegmentWarning;
use App\Modules\CSms\Ui\DonottextList;
use App\Modules\CSms\Ui\PernumberComplaintMonitoring;
use App\Modules\CSms\Ui\Thread;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-sms')->group(function () {
    Route::get('/thread', Thread::class)->name('c-sms.thread');
    Route::get('/composer-segment-warning', ComposerSegmentWarning::class)->name('c-sms.composer-segment-warning');
    Route::get('/donottext-list', DonottextList::class)->name('c-sms.donottext-list');
    Route::get('/pernumber-complaint-monitoring', PernumberComplaintMonitoring::class)->name('c-sms.pernumber-complaint-monitoring');
});
