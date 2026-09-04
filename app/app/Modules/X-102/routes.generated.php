<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-102')->group(function () {
    Route::get('/x-102/customerfacing-widget', \App\Modules\X102\Ui\CustomerfacingWidget::class)->name('x-102.customerfacing-widget');
    Route::get('/x-102/thread', \App\Modules\X102\Ui\Thread::class)->name('x-102.thread');
    Route::get('/x-102/offline-form-inbox', \App\Modules\X102\Ui\OfflineFormInbox::class)->name('x-102.offline-form-inbox');
    Route::get('/x-102/rageclick-rate', \App\Modules\X102\Ui\RageclickRate::class)->name('x-102.rageclick-rate');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-102')->group(function () {
    Route::get('/x-102/customerfacing-widget', \App\Modules\X102\Ui\CustomerfacingWidget::class)->name('x-102.customerfacing-widget');
    Route::get('/x-102/thread', \App\Modules\X102\Ui\Thread::class)->name('x-102.thread');
    Route::get('/x-102/offline-form-inbox', \App\Modules\X102\Ui\OfflineFormInbox::class)->name('x-102.offline-form-inbox');
    Route::get('/x-102/rageclick-rate', \App\Modules\X102\Ui\RageclickRate::class)->name('x-102.rageclick-rate');
});

