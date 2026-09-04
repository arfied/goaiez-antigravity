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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-66')->group(function () {
    Route::get('/x-66/calls', \App\Modules\X66\Ui\Calls::class)->name('x-66.calls');
    Route::get('/x-66/livecoaching-whisper-panel', \App\Modules\X66\Ui\LivecoachingWhisperPanel::class)->name('x-66.livecoaching-whisper-panel');
    Route::get('/x-66/customerfacing-call-itself', \App\Modules\X66\Ui\CustomerfacingCallItself::class)->name('x-66.customerfacing-call-itself');
    Route::get('/x-66/latency-p50p95-per', \App\Modules\X66\Ui\LatencyP50p95Per::class)->name('x-66.latency-p50p95-per');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-66')->group(function () {
    Route::get('/x-66/calls', \App\Modules\X66\Ui\Calls::class)->name('x-66.calls');
    Route::get('/x-66/livecoaching-whisper-panel', \App\Modules\X66\Ui\LivecoachingWhisperPanel::class)->name('x-66.livecoaching-whisper-panel');
    Route::get('/x-66/customerfacing-call-itself', \App\Modules\X66\Ui\CustomerfacingCallItself::class)->name('x-66.customerfacing-call-itself');
    Route::get('/x-66/latency-p50p95-per', \App\Modules\X66\Ui\LatencyP50p95Per::class)->name('x-66.latency-p50p95-per');
});

