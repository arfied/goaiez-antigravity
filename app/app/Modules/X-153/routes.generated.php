<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-153')->group(function () {
    Route::get('/alert-reply-by', \App\Modules\X153\Ui\AlertReplyBy::class)->name('x-153.alert-reply-by');
    Route::get('/alert-roster-screen', \App\Modules\X153\Ui\AlertRosterScreen::class)->name('x-153.alert-roster-screen');
    Route::get('/claimexpiry-rate', \App\Modules\X153\Ui\ClaimexpiryRate::class)->name('x-153.claimexpiry-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-153')->group(function () {
    Route::get('/alert-reply-by', \App\Modules\X153\Ui\AlertReplyBy::class)->name('x-153.alert-reply-by.admin');
    Route::get('/alert-roster-screen', \App\Modules\X153\Ui\AlertRosterScreen::class)->name('x-153.alert-roster-screen.admin');
    Route::get('/claimexpiry-rate', \App\Modules\X153\Ui\ClaimexpiryRate::class)->name('x-153.claimexpiry-rate.admin');
});

