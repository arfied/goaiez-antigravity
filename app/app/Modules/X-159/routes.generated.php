<?php

declare(strict_types=1);

use App\Modules\X159\Ui\AuditQueue;
use App\Modules\X159\Ui\AuditScore;
use App\Modules\X159\Ui\CustomerprospectfacingAuditPage;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-159')->group(function () {
    Route::get('/customerprospectfacing-audit-page', CustomerprospectfacingAuditPage::class)->name('x-159.customerprospectfacing-audit-page');
    Route::get('/score', AuditScore::class)->name('x-159.score');
    Route::get('/audit-queue', AuditQueue::class)->name('x-159.audit-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-159')->group(function () {
    Route::get('/customerprospectfacing-audit-page', CustomerprospectfacingAuditPage::class)->name('x-159.customerprospectfacing-audit-page.admin');
    Route::get('/score', AuditScore::class)->name('x-159.score.admin');
    Route::get('/audit-queue', AuditQueue::class)->name('x-159.audit-queue.admin');
});
