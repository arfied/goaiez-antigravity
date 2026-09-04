<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-155')->group(function () {
    Route::get('/x-155/forms', \App\Modules\X155\Ui\Forms::class)->name('x-155.forms');
    Route::get('/x-155/submissions-thread', \App\Modules\X155\Ui\SubmissionsThread::class)->name('x-155.submissions-thread');
    Route::get('/x-155/spam-rate', \App\Modules\X155\Ui\SpamRate::class)->name('x-155.spam-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-155')->group(function () {
    Route::get('/x-155/forms', \App\Modules\X155\Ui\Forms::class)->name('x-155.forms');
    Route::get('/x-155/submissions-thread', \App\Modules\X155\Ui\SubmissionsThread::class)->name('x-155.submissions-thread');
    Route::get('/x-155/spam-rate', \App\Modules\X155\Ui\SpamRate::class)->name('x-155.spam-rate');
});

