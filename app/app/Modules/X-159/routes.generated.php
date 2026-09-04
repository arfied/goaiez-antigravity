<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-159')->group(function () {
    Route::get('/customerprospectfacing-audit-page', \App\Modules\X159\Ui\CustomerprospectfacingAuditPage::class)->name('x-159.customerprospectfacing-audit-page');
    Route::get('/score', \App\Modules\X159\Ui\AuditScore::class)->name('x-159.score');
    Route::get('/audit-queue', \App\Modules\X159\Ui\AuditQueue::class)->name('x-159.audit-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-159')->group(function () {
    Route::get('/customerprospectfacing-audit-page', \App\Modules\X159\Ui\CustomerprospectfacingAuditPage::class)->name('x-159.customerprospectfacing-audit-page.admin');
    Route::get('/score', \App\Modules\X159\Ui\AuditScore::class)->name('x-159.score.admin');
    Route::get('/audit-queue', \App\Modules\X159\Ui\AuditQueue::class)->name('x-159.audit-queue.admin');
});

