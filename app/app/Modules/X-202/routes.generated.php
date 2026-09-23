<?php

declare(strict_types=1);

use App\Modules\X202\Ui\AuditExport;
use App\Modules\X202\Ui\Item;
use App\Modules\X202\Ui\Mobile;
use App\Modules\X202\Ui\Queue;
use App\Modules\X202\Ui\Slack;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-202')->group(function () {
    Route::get('/queue', Queue::class)->name('x-202.queue');
    Route::get('/item', Item::class)->name('x-202.item');
    Route::get('/audit-export', AuditExport::class)->name('x-202.audit-export');
    Route::get('/mobile', Mobile::class)->name('x-202.mobile');
});
