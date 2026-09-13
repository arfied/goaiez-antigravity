<?php

declare(strict_types=1);

use App\Modules\X124\Ui\AssistantunsupportedLog;
use App\Modules\X124\Ui\ChatDockEvery;
use App\Modules\X124\Ui\PreviewCard;
use App\Modules\X124\Ui\TodaysRecommendationStrip;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-124')->group(function () {
    Route::get('/chat-dock-every', ChatDockEvery::class)->name('x-124.chat-dock-every.admin');
    Route::get('/preview-card', PreviewCard::class)->name('x-124.preview-card.admin');
    Route::get('/todays-recommendation-strip', TodaysRecommendationStrip::class)->name('x-124.todays-recommendation-strip.admin');
    Route::get('/assistantunsupported-log', AssistantunsupportedLog::class)->name('x-124.assistantunsupported-log.admin');
});
Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-124')->group(function () {
    Route::get('/assistantunsupported-log', AssistantunsupportedLog::class)
        ->name('x-124.assistantunsupported-log');
    Route::get('/preview-card', PreviewCard::class)->name('x-124.preview-card');
});
