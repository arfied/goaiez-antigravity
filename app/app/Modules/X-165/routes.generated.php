<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-165')->group(function () {
    Route::get('/plans', \App\Modules\X165\Ui\Plans::class)->name('x-165.plans');
    Route::get('/members', \App\Modules\X165\Ui\Members::class)->name('x-165.members');
});

