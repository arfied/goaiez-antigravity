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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-209')->group(function () {
    Route::get('/x-209/private-inbox', \App\Modules\X209\Ui\PrivateInbox::class)->name('x-209.private-inbox');
    Route::get('/x-209/onetap-approval-card', \App\Modules\X209\Ui\OnetapApprovalCard::class)->name('x-209.onetap-approval-card');
    Route::get('/x-209/ladders-own-state', \App\Modules\X209\Ui\LaddersOwnState::class)->name('x-209.ladders-own-state');
    Route::get('/x-209/never-setting', \App\Modules\X209\Ui\NeverSetting::class)->name('x-209.never-setting');
});

