<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\CWhatsapp\Ui\TemplateApprovalQueue;
use App\Modules\CWhatsapp\Ui\TemplateStatusCard;
use App\Modules\CWhatsapp\Ui\Thread;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-whatsapp')->group(function () {
    Route::get('/c-whatsapp/thread', Thread::class)->name('c-whatsapp.thread');
    Route::get('/c-whatsapp/template-status-card', TemplateStatusCard::class)->name('c-whatsapp.template-status-card');
    Route::get('/c-whatsapp/template-approval-queue', TemplateApprovalQueue::class)->name('c-whatsapp.template-approval-queue');
});
