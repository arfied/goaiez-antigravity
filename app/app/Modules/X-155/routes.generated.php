<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X155\Ui\Forms;
use App\Modules\X155\Ui\SpamRate;
use App\Modules\X155\Ui\SubmissionsThread;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-155')->group(function () {
    Route::get('/x-155/forms', Forms::class)->name('x-155.forms');
    Route::get('/x-155/submissions-thread', SubmissionsThread::class)->name('x-155.submissions-thread');
    Route::get('/x-155/spam-rate', SpamRate::class)->name('x-155.spam-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-155')->group(function () {
    Route::get('/x-155/forms', Forms::class)->name('x-155.forms');
    Route::get('/x-155/submissions-thread', SubmissionsThread::class)->name('x-155.submissions-thread');
    Route::get('/x-155/spam-rate', SpamRate::class)->name('x-155.spam-rate');
});
