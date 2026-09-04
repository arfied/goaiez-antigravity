<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X159\Ui\AuditQueue;
use App\Modules\X159\Ui\AuditScore;
use App\Modules\X159\Ui\CustomerprospectfacingAuditPage;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-159')->group(function () {
    Route::get('/x-159/customerprospectfacing-audit-page', CustomerprospectfacingAuditPage::class)->name('x-159.customerprospectfacing-audit-page');
    Route::get('/x-159/score', AuditScore::class)->name('x-159.score');
    Route::get('/x-159/audit-queue', AuditQueue::class)->name('x-159.audit-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-159')->group(function () {
    Route::get('/x-159/customerprospectfacing-audit-page', CustomerprospectfacingAuditPage::class)->name('x-159.customerprospectfacing-audit-page');
    Route::get('/x-159/score', AuditScore::class)->name('x-159.score');
    Route::get('/x-159/audit-queue', AuditQueue::class)->name('x-159.audit-queue');
});
