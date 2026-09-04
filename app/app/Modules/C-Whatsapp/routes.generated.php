<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-whatsapp')->group(function () {
    Route::get('/thread', \App\Modules\CWhatsapp\Ui\Thread::class)->name('c-whatsapp.thread');
    Route::get('/template-status-card', \App\Modules\CWhatsapp\Ui\TemplateStatusCard::class)->name('c-whatsapp.template-status-card');
    Route::get('/template-approval-queue', \App\Modules\CWhatsapp\Ui\TemplateApprovalQueue::class)->name('c-whatsapp.template-approval-queue');
});

