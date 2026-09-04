<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X219\Ui\AssignmentMatrix;
use App\Modules\X219\Ui\RosterAdmin;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-219')->group(function () {
    Route::get('/x-219/roster-admin', RosterAdmin::class)->name('x-219.roster-admin');
    Route::get('/x-219/assignment-matrix', AssignmentMatrix::class)->name('x-219.assignment-matrix');
});
