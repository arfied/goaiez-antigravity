<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X200\Ui\AbandonmentComplaintRates;
use App\Modules\X200\Ui\AgentDesktop;
use App\Modules\X200\Ui\CampaignBoard;
use App\Modules\X200\Ui\CustomerfacingNone;
use App\Modules\X200\Ui\QaScorecardView;
use App\Modules\X200\Ui\Wallboard;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-200')->group(function () {
    Route::get('/x-200/agent-desktop', AgentDesktop::class)->name('x-200.agent-desktop');
    Route::get('/x-200/campaign-board', CampaignBoard::class)->name('x-200.campaign-board');
    Route::get('/x-200/wallboard', Wallboard::class)->name('x-200.wallboard');
    Route::get('/x-200/qa-scorecard', QaScorecardView::class)->name('x-200.qa-scorecard');
    Route::get('/x-200/customerfacing-none', CustomerfacingNone::class)->name('x-200.customerfacing-none');
    Route::get('/x-200/abandonment-complaint-rates', AbandonmentComplaintRates::class)->name('x-200.abandonment-complaint-rates');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-200')->group(function () {
    Route::get('/x-200/agent-desktop', AgentDesktop::class)->name('x-200.agent-desktop');
    Route::get('/x-200/campaign-board', CampaignBoard::class)->name('x-200.campaign-board');
    Route::get('/x-200/wallboard', Wallboard::class)->name('x-200.wallboard');
    Route::get('/x-200/qa-scorecard', QaScorecardView::class)->name('x-200.qa-scorecard');
    Route::get('/x-200/customerfacing-none', CustomerfacingNone::class)->name('x-200.customerfacing-none');
    Route::get('/x-200/abandonment-complaint-rates', AbandonmentComplaintRates::class)->name('x-200.abandonment-complaint-rates');
});
