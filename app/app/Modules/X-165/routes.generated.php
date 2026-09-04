<?php

declare(strict_types=1);

use App\Modules\X165\Ui\Members;
use App\Modules\X165\Ui\Plans;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-165')->group(function () {
    Route::get('/plans', Plans::class)->name('x-165.plans');
    Route::get('/members', Members::class)->name('x-165.members');
});
