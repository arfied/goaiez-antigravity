<?php

declare(strict_types=1);

use App\Modules\X150\Ui\ProviderCostPer;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-150')->group(function () {
    Route::get('/provider-cost-per', ProviderCostPer::class)->name('x-150.provider-cost-per.admin');
});
