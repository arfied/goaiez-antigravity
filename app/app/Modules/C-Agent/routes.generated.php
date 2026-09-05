<?php

declare(strict_types=1);

use App\Modules\CAgent\Ui\GroundcheckScreen;
use App\Modules\CAgent\Ui\RefusalcodeDistributionPer;
use App\Modules\CAgent\Ui\TeachingBox;
use App\Modules\CAgent\Ui\Thread;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-agent')->group(function () {
    Route::get('/thread', Thread::class)->name('c-agent.thread');
    Route::get('/groundcheck-screen', GroundcheckScreen::class)->name('c-agent.groundcheck-screen');
    Route::get('/teaching-box', TeachingBox::class)->name('c-agent.teaching-box');
    Route::get('/refusalcode-distribution-per', RefusalcodeDistributionPer::class)->name('c-agent.refusalcode-distribution-per');
});
