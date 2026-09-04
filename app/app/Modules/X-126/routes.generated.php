<?php

declare(strict_types=1);

use App\Modules\X126\Ui\RefusalAnalytics;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-126')->group(function () {
    Route::get('/refusal-analytics', RefusalAnalytics::class)->name('x-126.refusal-analytics.admin');
});
