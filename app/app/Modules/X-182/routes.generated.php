<?php

declare(strict_types=1);

use App\Modules\X182\Ui\ConnectedAccounts;
use App\Modules\X182\Ui\SocialQueue;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-182')->group(function () {
    Route::get('/connected-accounts', ConnectedAccounts::class)->name('x-182.connected-accounts');
    Route::get('/social-queue', SocialQueue::class)->name('x-182.social-queue');
});
