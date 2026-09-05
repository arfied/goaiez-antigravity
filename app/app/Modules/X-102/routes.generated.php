<?php

declare(strict_types=1);

use App\Modules\X102\Ui\CustomerfacingWidget;
use App\Modules\X102\Ui\OfflineFormInbox;
use App\Modules\X102\Ui\RageclickRate;
use App\Modules\X102\Ui\Thread;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-102')->group(function () {
    Route::get('/customerfacing-widget', CustomerfacingWidget::class)->name('x-102.customerfacing-widget');
    Route::get('/thread', Thread::class)->name('x-102.thread');
    Route::get('/offline-form-inbox', OfflineFormInbox::class)->name('x-102.offline-form-inbox');
    Route::get('/rageclick-rate', RageclickRate::class)->name('x-102.rageclick-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-102')->group(function () {
    Route::get('/customerfacing-widget', CustomerfacingWidget::class)->name('x-102.customerfacing-widget.admin');
    Route::get('/thread', Thread::class)->name('x-102.thread.admin');
    Route::get('/offline-form-inbox', OfflineFormInbox::class)->name('x-102.offline-form-inbox.admin');
    Route::get('/rageclick-rate', RageclickRate::class)->name('x-102.rageclick-rate.admin');
});
