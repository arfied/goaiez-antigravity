<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X102\Ui\CustomerfacingWidget;
use App\Modules\X102\Ui\OfflineFormInbox;
use App\Modules\X102\Ui\RageclickRate;
use App\Modules\X102\Ui\Thread;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-102')->group(function () {
    Route::get('/x-102/customerfacing-widget', CustomerfacingWidget::class)->name('x-102.customerfacing-widget');
    Route::get('/x-102/thread', Thread::class)->name('x-102.thread');
    Route::get('/x-102/offline-form-inbox', OfflineFormInbox::class)->name('x-102.offline-form-inbox');
    Route::get('/x-102/rageclick-rate', RageclickRate::class)->name('x-102.rageclick-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-102')->group(function () {
    Route::get('/x-102/customerfacing-widget', CustomerfacingWidget::class)->name('x-102.customerfacing-widget');
    Route::get('/x-102/thread', Thread::class)->name('x-102.thread');
    Route::get('/x-102/offline-form-inbox', OfflineFormInbox::class)->name('x-102.offline-form-inbox');
    Route::get('/x-102/rageclick-rate', RageclickRate::class)->name('x-102.rageclick-rate');
});
