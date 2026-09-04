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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/c-agent')->group(function () {
    Route::get('/c-agent/thread', \App\Modules\CAgent\Ui\Thread::class)->name('c-agent.thread');
    Route::get('/c-agent/groundcheck-screen', \App\Modules\CAgent\Ui\GroundcheckScreen::class)->name('c-agent.groundcheck-screen');
    Route::get('/c-agent/teaching-box', \App\Modules\CAgent\Ui\TeachingBox::class)->name('c-agent.teaching-box');
    Route::get('/c-agent/refusalcode-distribution-per', \App\Modules\CAgent\Ui\RefusalcodeDistributionPer::class)->name('c-agent.refusalcode-distribution-per');
});

