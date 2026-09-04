<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-219')->group(function () {
    Route::get('/roster-admin', \App\Modules\X219\Ui\RosterAdmin::class)->name('x-219.roster-admin');
    Route::get('/assignment-matrix', \App\Modules\X219\Ui\AssignmentMatrix::class)->name('x-219.assignment-matrix');
});

