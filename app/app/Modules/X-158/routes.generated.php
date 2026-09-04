<?php

declare(strict_types=1);

use App\Modules\X158\Ui\ContentPlansVideo;
use App\Modules\X158\Ui\ProspectfacingVideoDemo;
use App\Modules\X158\Ui\RenderQueue;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-158')->group(function () {
    Route::get('/prospectfacing-video-demo', ProspectfacingVideoDemo::class)->name('x-158.prospectfacing-video-demo');
    Route::get('/content-plans-video', ContentPlansVideo::class)->name('x-158.content-plans-video');
    Route::get('/render-queue', RenderQueue::class)->name('x-158.render-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-158')->group(function () {
    Route::get('/prospectfacing-video-demo', ProspectfacingVideoDemo::class)->name('x-158.prospectfacing-video-demo.admin');
    Route::get('/content-plans-video', ContentPlansVideo::class)->name('x-158.content-plans-video.admin');
    Route::get('/render-queue', RenderQueue::class)->name('x-158.render-queue.admin');
});
