<?php

declare(strict_types=1);

use App\Modules\X172\Ui\CustomerfacingPortal;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-172')->group(function () {
    Route::get('/customerfacing-portal/{token}', CustomerfacingPortal::class)->name('x-172.customerfacing-portal');
});
