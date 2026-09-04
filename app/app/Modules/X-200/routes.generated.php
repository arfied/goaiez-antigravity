<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-200')->group(function () {
    Route::get('/agent-desktop', \App\Modules\X200\Ui\AgentDesktop::class)->name('x-200.agent-desktop');
    Route::get('/campaign-board', \App\Modules\X200\Ui\CampaignBoard::class)->name('x-200.campaign-board');
    Route::get('/wallboard', \App\Modules\X200\Ui\Wallboard::class)->name('x-200.wallboard');
    Route::get('/qa-scorecard', \App\Modules\X200\Ui\QaScorecardView::class)->name('x-200.qa-scorecard');
    Route::get('/customerfacing-none', \App\Modules\X200\Ui\CustomerfacingNone::class)->name('x-200.customerfacing-none');
    Route::get('/abandonment-complaint-rates', \App\Modules\X200\Ui\AbandonmentComplaintRates::class)->name('x-200.abandonment-complaint-rates');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-200')->group(function () {
    Route::get('/agent-desktop', \App\Modules\X200\Ui\AgentDesktop::class)->name('x-200.agent-desktop.admin');
    Route::get('/campaign-board', \App\Modules\X200\Ui\CampaignBoard::class)->name('x-200.campaign-board.admin');
    Route::get('/wallboard', \App\Modules\X200\Ui\Wallboard::class)->name('x-200.wallboard.admin');
    Route::get('/qa-scorecard', \App\Modules\X200\Ui\QaScorecardView::class)->name('x-200.qa-scorecard.admin');
    Route::get('/customerfacing-none', \App\Modules\X200\Ui\CustomerfacingNone::class)->name('x-200.customerfacing-none.admin');
    Route::get('/abandonment-complaint-rates', \App\Modules\X200\Ui\AbandonmentComplaintRates::class)->name('x-200.abandonment-complaint-rates.admin');
});

