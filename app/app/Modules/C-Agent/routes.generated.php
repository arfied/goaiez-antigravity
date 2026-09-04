<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\CAgent\Ui\GroundcheckScreen;
use App\Modules\CAgent\Ui\RefusalcodeDistributionPer;
use App\Modules\CAgent\Ui\TeachingBox;
use App\Modules\CAgent\Ui\Thread;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-agent')->group(function () {
    Route::get('/c-agent/thread', Thread::class)->name('c-agent.thread');
    Route::get('/c-agent/groundcheck-screen', GroundcheckScreen::class)->name('c-agent.groundcheck-screen');
    Route::get('/c-agent/teaching-box', TeachingBox::class)->name('c-agent.teaching-box');
    Route::get('/c-agent/refusalcode-distribution-per', RefusalcodeDistributionPer::class)->name('c-agent.refusalcode-distribution-per');
});
