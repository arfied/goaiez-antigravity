<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X66\Ui\Calls;
use App\Modules\X66\Ui\CustomerfacingCallItself;
use App\Modules\X66\Ui\LatencyP50p95Per;
use App\Modules\X66\Ui\LivecoachingWhisperPanel;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-66')->group(function () {
    Route::get('/x-66/calls', Calls::class)->name('x-66.calls');
    Route::get('/x-66/livecoaching-whisper-panel', LivecoachingWhisperPanel::class)->name('x-66.livecoaching-whisper-panel');
    Route::get('/x-66/customerfacing-call-itself', CustomerfacingCallItself::class)->name('x-66.customerfacing-call-itself');
    Route::get('/x-66/latency-p50p95-per', LatencyP50p95Per::class)->name('x-66.latency-p50p95-per');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-66')->group(function () {
    Route::get('/x-66/calls', Calls::class)->name('x-66.calls');
    Route::get('/x-66/livecoaching-whisper-panel', LivecoachingWhisperPanel::class)->name('x-66.livecoaching-whisper-panel');
    Route::get('/x-66/customerfacing-call-itself', CustomerfacingCallItself::class)->name('x-66.customerfacing-call-itself');
    Route::get('/x-66/latency-p50p95-per', LatencyP50p95Per::class)->name('x-66.latency-p50p95-per');
});
