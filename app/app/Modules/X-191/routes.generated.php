<?php

declare(strict_types=1);

use App\Modules\X191\Ui\LinksEarned;
use App\Modules\X191\Ui\PitchacquireRatio;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-191')->group(function () {
    Route::get('/links-earned', LinksEarned::class)->name('x-191.links-earned');
    Route::get('/pitchacquire-ratio', PitchacquireRatio::class)->name('x-191.pitchacquire-ratio');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-191')->group(function () {
    Route::get('/links-earned', LinksEarned::class)->name('x-191.links-earned.admin');
    Route::get('/pitchacquire-ratio', PitchacquireRatio::class)->name('x-191.pitchacquire-ratio.admin');
});
