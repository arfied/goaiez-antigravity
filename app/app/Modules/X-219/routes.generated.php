<?php

declare(strict_types=1);

use App\Modules\X219\Ui\AssignmentMatrix;
use App\Modules\X219\Ui\RosterAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-219')->group(function () {
    Route::get('/roster-admin', RosterAdmin::class)->name('x-219.roster-admin');
    Route::get('/assignment-matrix', AssignmentMatrix::class)->name('x-219.assignment-matrix');
});
