<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-124')->group(function () {
    Route::get('/chat-dock-every', \App\Modules\X124\Ui\ChatDockEvery::class)->name('x-124.chat-dock-every.admin');
    Route::get('/preview-card', \App\Modules\X124\Ui\PreviewCard::class)->name('x-124.preview-card.admin');
    Route::get('/todays-recommendation-strip', \App\Modules\X124\Ui\TodaysRecommendationStrip::class)->name('x-124.todays-recommendation-strip.admin');
    Route::get('/assistantunsupported-log', \App\Modules\X124\Ui\AssistantunsupportedLog::class)->name('x-124.assistantunsupported-log.admin');
});

