<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-191')->group(function () {
    Route::get('/links-earned', \App\Modules\X191\Ui\LinksEarned::class)->name('x-191.links-earned');
    Route::get('/pitchacquire-ratio', \App\Modules\X191\Ui\PitchacquireRatio::class)->name('x-191.pitchacquire-ratio');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-191')->group(function () {
    Route::get('/links-earned', \App\Modules\X191\Ui\LinksEarned::class)->name('x-191.links-earned.admin');
    Route::get('/pitchacquire-ratio', \App\Modules\X191\Ui\PitchacquireRatio::class)->name('x-191.pitchacquire-ratio.admin');
});

