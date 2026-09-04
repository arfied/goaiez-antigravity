<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-159')->group(function () {
    Route::get('/x-159/customerprospectfacing-audit-page', \App\Modules\X159\Ui\CustomerprospectfacingAuditPage::class)->name('x-159.customerprospectfacing-audit-page');
    Route::get('/x-159/score', \App\Modules\X159\Ui\AuditScore::class)->name('x-159.score');
    Route::get('/x-159/audit-queue', \App\Modules\X159\Ui\AuditQueue::class)->name('x-159.audit-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-159')->group(function () {
    Route::get('/x-159/customerprospectfacing-audit-page', \App\Modules\X159\Ui\CustomerprospectfacingAuditPage::class)->name('x-159.customerprospectfacing-audit-page');
    Route::get('/x-159/score', \App\Modules\X159\Ui\AuditScore::class)->name('x-159.score');
    Route::get('/x-159/audit-queue', \App\Modules\X159\Ui\AuditQueue::class)->name('x-159.audit-queue');
});

