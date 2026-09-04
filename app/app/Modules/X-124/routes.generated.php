<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-124')->group(function () {
    Route::get('/x-124/chat-dock-every', \App\Modules\X124\Ui\ChatDockEvery::class)->name('x-124.chat-dock-every');
    Route::get('/x-124/preview-card', \App\Modules\X124\Ui\PreviewCard::class)->name('x-124.preview-card');
    Route::get('/x-124/todays-recommendation-strip', \App\Modules\X124\Ui\TodaysRecommendationStrip::class)->name('x-124.todays-recommendation-strip');
    Route::get('/x-124/assistantunsupported-log', \App\Modules\X124\Ui\AssistantunsupportedLog::class)->name('x-124.assistantunsupported-log');
});

