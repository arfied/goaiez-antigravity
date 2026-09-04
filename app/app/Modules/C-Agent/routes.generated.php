<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-agent')->group(function () {
    Route::get('/thread', \App\Modules\CAgent\Ui\Thread::class)->name('c-agent.thread');
    Route::get('/groundcheck-screen', \App\Modules\CAgent\Ui\GroundcheckScreen::class)->name('c-agent.groundcheck-screen');
    Route::get('/teaching-box', \App\Modules\CAgent\Ui\TeachingBox::class)->name('c-agent.teaching-box');
    Route::get('/refusalcode-distribution-per', \App\Modules\CAgent\Ui\RefusalcodeDistributionPer::class)->name('c-agent.refusalcode-distribution-per');
});

