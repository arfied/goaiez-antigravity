<?php

declare(strict_types=1);

use App\Modules\X66\Ui\Calls;
use App\Modules\X66\Ui\LatencyP50p95Per;
use App\Modules\X66\Ui\LivecoachingWhisperPanel;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-66')->group(function () {
    Route::get('/calls', Calls::class)->name('x-66.calls');
    Route::get('/livecoaching-whisper-panel', LivecoachingWhisperPanel::class)->name('x-66.livecoaching-whisper-panel');
    Route::get('/latency-p50p95-per', LatencyP50p95Per::class)->name('x-66.latency-p50p95-per');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-66')->group(function () {
    Route::get('/calls', Calls::class)->name('x-66.calls.admin');
    Route::get('/livecoaching-whisper-panel', LivecoachingWhisperPanel::class)->name('x-66.livecoaching-whisper-panel.admin');
    Route::get('/latency-p50p95-per', LatencyP50p95Per::class)->name('x-66.latency-p50p95-per.admin');
});
