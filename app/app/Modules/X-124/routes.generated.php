<?php

declare(strict_types=1);

use App\Modules\X124\Ui\AssistantunsupportedLog;
use App\Modules\X124\Ui\ChatDockEvery;
use App\Modules\X124\Ui\PreviewCard;
use App\Modules\X124\Ui\TodaysRecommendationStrip;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-124')->group(function () {
    Route::get('/x-124/chat-dock-every', ChatDockEvery::class)->name('x-124.chat-dock-every');
    Route::get('/x-124/preview-card', PreviewCard::class)->name('x-124.preview-card');
    Route::get('/x-124/todays-recommendation-strip', TodaysRecommendationStrip::class)->name('x-124.todays-recommendation-strip');
    Route::get('/x-124/assistantunsupported-log', AssistantunsupportedLog::class)->name('x-124.assistantunsupported-log');
});
