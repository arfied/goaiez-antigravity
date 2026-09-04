<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X153\Ui\AlertReplyBy;
use App\Modules\X153\Ui\AlertRosterScreen;
use App\Modules\X153\Ui\ClaimexpiryRate;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-153')->group(function () {
    Route::get('/x-153/alert-reply-by', AlertReplyBy::class)->name('x-153.alert-reply-by');
    Route::get('/x-153/alert-roster-screen', AlertRosterScreen::class)->name('x-153.alert-roster-screen');
    Route::get('/x-153/claimexpiry-rate', ClaimexpiryRate::class)->name('x-153.claimexpiry-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-153')->group(function () {
    Route::get('/x-153/alert-reply-by', AlertReplyBy::class)->name('x-153.alert-reply-by');
    Route::get('/x-153/alert-roster-screen', AlertRosterScreen::class)->name('x-153.alert-roster-screen');
    Route::get('/x-153/claimexpiry-rate', ClaimexpiryRate::class)->name('x-153.claimexpiry-rate');
});
