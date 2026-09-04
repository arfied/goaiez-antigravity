<?php

declare(strict_types=1);

use App\Modules\X200\Ui\AbandonmentComplaintRates;
use App\Modules\X200\Ui\AgentDesktop;
use App\Modules\X200\Ui\CampaignBoard;
use App\Modules\X200\Ui\CustomerfacingNone;
use App\Modules\X200\Ui\QaScorecardView;
use App\Modules\X200\Ui\Wallboard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-200')->group(function () {
    Route::get('/agent-desktop', AgentDesktop::class)->name('x-200.agent-desktop');
    Route::get('/campaign-board', CampaignBoard::class)->name('x-200.campaign-board');
    Route::get('/wallboard', Wallboard::class)->name('x-200.wallboard');
    Route::get('/qa-scorecard', QaScorecardView::class)->name('x-200.qa-scorecard');
    Route::get('/customerfacing-none', CustomerfacingNone::class)->name('x-200.customerfacing-none');
    Route::get('/abandonment-complaint-rates', AbandonmentComplaintRates::class)->name('x-200.abandonment-complaint-rates');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-200')->group(function () {
    Route::get('/agent-desktop', AgentDesktop::class)->name('x-200.agent-desktop.admin');
    Route::get('/campaign-board', CampaignBoard::class)->name('x-200.campaign-board.admin');
    Route::get('/wallboard', Wallboard::class)->name('x-200.wallboard.admin');
    Route::get('/qa-scorecard', QaScorecardView::class)->name('x-200.qa-scorecard.admin');
    Route::get('/customerfacing-none', CustomerfacingNone::class)->name('x-200.customerfacing-none.admin');
    Route::get('/abandonment-complaint-rates', AbandonmentComplaintRates::class)->name('x-200.abandonment-complaint-rates.admin');
});
