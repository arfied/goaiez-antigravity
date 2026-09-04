<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-200')->group(function () {
    Route::get('/x-200/agent-desktop', \App\Modules\X200\Ui\AgentDesktop::class)->name('x-200.agent-desktop');
    Route::get('/x-200/campaign-board', \App\Modules\X200\Ui\CampaignBoard::class)->name('x-200.campaign-board');
    Route::get('/x-200/wallboard', \App\Modules\X200\Ui\Wallboard::class)->name('x-200.wallboard');
    Route::get('/x-200/qa-scorecard', \App\Modules\X200\Ui\QaScorecardView::class)->name('x-200.qa-scorecard');
    Route::get('/x-200/customerfacing-none', \App\Modules\X200\Ui\CustomerfacingNone::class)->name('x-200.customerfacing-none');
    Route::get('/x-200/abandonment-complaint-rates', \App\Modules\X200\Ui\AbandonmentComplaintRates::class)->name('x-200.abandonment-complaint-rates');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-200')->group(function () {
    Route::get('/x-200/agent-desktop', \App\Modules\X200\Ui\AgentDesktop::class)->name('x-200.agent-desktop');
    Route::get('/x-200/campaign-board', \App\Modules\X200\Ui\CampaignBoard::class)->name('x-200.campaign-board');
    Route::get('/x-200/wallboard', \App\Modules\X200\Ui\Wallboard::class)->name('x-200.wallboard');
    Route::get('/x-200/qa-scorecard', \App\Modules\X200\Ui\QaScorecardView::class)->name('x-200.qa-scorecard');
    Route::get('/x-200/customerfacing-none', \App\Modules\X200\Ui\CustomerfacingNone::class)->name('x-200.customerfacing-none');
    Route::get('/x-200/abandonment-complaint-rates', \App\Modules\X200\Ui\AbandonmentComplaintRates::class)->name('x-200.abandonment-complaint-rates');
});

