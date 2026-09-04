<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-153')->group(function () {
    Route::get('/x-153/alert-reply-by', \App\Modules\X153\Ui\AlertReplyBy::class)->name('x-153.alert-reply-by');
    Route::get('/x-153/alert-roster-screen', \App\Modules\X153\Ui\AlertRosterScreen::class)->name('x-153.alert-roster-screen');
    Route::get('/x-153/claimexpiry-rate', \App\Modules\X153\Ui\ClaimexpiryRate::class)->name('x-153.claimexpiry-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-153')->group(function () {
    Route::get('/x-153/alert-reply-by', \App\Modules\X153\Ui\AlertReplyBy::class)->name('x-153.alert-reply-by');
    Route::get('/x-153/alert-roster-screen', \App\Modules\X153\Ui\AlertRosterScreen::class)->name('x-153.alert-roster-screen');
    Route::get('/x-153/claimexpiry-rate', \App\Modules\X153\Ui\ClaimexpiryRate::class)->name('x-153.claimexpiry-rate');
});

