<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-150')->group(function () {
    Route::get('/provider-cost-per', \App\Modules\X150\Ui\ProviderCostPer::class)->name('x-150.provider-cost-per.admin');
});

