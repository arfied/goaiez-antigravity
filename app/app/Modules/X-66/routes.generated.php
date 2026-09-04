<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-66')->group(function () {
    Route::get('/calls', \App\Modules\X66\Ui\Calls::class)->name('x-66.calls');
    Route::get('/livecoaching-whisper-panel', \App\Modules\X66\Ui\LivecoachingWhisperPanel::class)->name('x-66.livecoaching-whisper-panel');
    Route::get('/customerfacing-call-itself', \App\Modules\X66\Ui\CustomerfacingCallItself::class)->name('x-66.customerfacing-call-itself');
    Route::get('/latency-p50p95-per', \App\Modules\X66\Ui\LatencyP50p95Per::class)->name('x-66.latency-p50p95-per');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-66')->group(function () {
    Route::get('/calls', \App\Modules\X66\Ui\Calls::class)->name('x-66.calls.admin');
    Route::get('/livecoaching-whisper-panel', \App\Modules\X66\Ui\LivecoachingWhisperPanel::class)->name('x-66.livecoaching-whisper-panel.admin');
    Route::get('/customerfacing-call-itself', \App\Modules\X66\Ui\CustomerfacingCallItself::class)->name('x-66.customerfacing-call-itself.admin');
    Route::get('/latency-p50p95-per', \App\Modules\X66\Ui\LatencyP50p95Per::class)->name('x-66.latency-p50p95-per.admin');
});

