<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-102')->group(function () {
    Route::get('/customerfacing-widget', \App\Modules\X102\Ui\CustomerfacingWidget::class)->name('x-102.customerfacing-widget');
    Route::get('/thread', \App\Modules\X102\Ui\Thread::class)->name('x-102.thread');
    Route::get('/offline-form-inbox', \App\Modules\X102\Ui\OfflineFormInbox::class)->name('x-102.offline-form-inbox');
    Route::get('/rageclick-rate', \App\Modules\X102\Ui\RageclickRate::class)->name('x-102.rageclick-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-102')->group(function () {
    Route::get('/customerfacing-widget', \App\Modules\X102\Ui\CustomerfacingWidget::class)->name('x-102.customerfacing-widget.admin');
    Route::get('/thread', \App\Modules\X102\Ui\Thread::class)->name('x-102.thread.admin');
    Route::get('/offline-form-inbox', \App\Modules\X102\Ui\OfflineFormInbox::class)->name('x-102.offline-form-inbox.admin');
    Route::get('/rageclick-rate', \App\Modules\X102\Ui\RageclickRate::class)->name('x-102.rageclick-rate.admin');
});

