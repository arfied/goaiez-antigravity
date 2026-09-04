<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-192')->group(function () {
    Route::get('/memberships-list', \App\Modules\X192\Ui\MembershipsList::class)->name('x-192.memberships-list');
});

