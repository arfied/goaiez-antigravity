<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X209\Ui\LaddersOwnState;
use App\Modules\X209\Ui\NeverSetting;
use App\Modules\X209\Ui\OnetapApprovalCard;
use App\Modules\X209\Ui\PrivateInbox;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-209')->group(function () {
    Route::get('/x-209/private-inbox', PrivateInbox::class)->name('x-209.private-inbox');
    Route::get('/x-209/onetap-approval-card', OnetapApprovalCard::class)->name('x-209.onetap-approval-card');
    Route::get('/x-209/ladders-own-state', LaddersOwnState::class)->name('x-209.ladders-own-state');
    Route::get('/x-209/never-setting', NeverSetting::class)->name('x-209.never-setting');
});
