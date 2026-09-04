<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-202')->group(function () {
    Route::get('/queue', \App\Modules\X202\Ui\Queue::class)->name('x-202.queue');
    Route::get('/item', \App\Modules\X202\Ui\Item::class)->name('x-202.item');
    Route::get('/audit-export', \App\Modules\X202\Ui\AuditExport::class)->name('x-202.audit-export');
    Route::get('/mobile', \App\Modules\X202\Ui\Mobile::class)->name('x-202.mobile');
    Route::get('/slack', \App\Modules\X202\Ui\Slack::class)->name('x-202.slack');
});

