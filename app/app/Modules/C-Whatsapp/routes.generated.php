<?php

declare(strict_types=1);

use App\Modules\CWhatsapp\Ui\TemplateApprovalQueue;
use App\Modules\CWhatsapp\Ui\TemplateStatusCard;
use App\Modules\CWhatsapp\Ui\Thread;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-whatsapp')->group(function () {
    Route::get('/thread', Thread::class)->name('c-whatsapp.thread');
    Route::get('/template-status-card', TemplateStatusCard::class)->name('c-whatsapp.template-status-card');
    Route::get('/template-approval-queue', TemplateApprovalQueue::class)->name('c-whatsapp.template-approval-queue');
});
