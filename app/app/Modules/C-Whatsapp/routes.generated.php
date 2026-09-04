<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-whatsapp')->group(function () {
    Route::get('/c-whatsapp/thread', \App\Modules\CWhatsapp\Ui\Thread::class)->name('c-whatsapp.thread');
    Route::get('/c-whatsapp/template-status-card', \App\Modules\CWhatsapp\Ui\TemplateStatusCard::class)->name('c-whatsapp.template-status-card');
    Route::get('/c-whatsapp/template-approval-queue', \App\Modules\CWhatsapp\Ui\TemplateApprovalQueue::class)->name('c-whatsapp.template-approval-queue');
});

