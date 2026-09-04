<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-126')->group(function () {
    Route::get('/x-126/refusal-analytics', \App\Modules\X126\Ui\RefusalAnalytics::class)->name('x-126.refusal-analytics');
});

