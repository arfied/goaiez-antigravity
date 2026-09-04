<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-215')->group(function () {
    Route::get('/x-215/document-status', \App\Modules\X215\Ui\DocumentStatus::class)->name('x-215.document-status');
    Route::get('/x-215/signature-pad', \App\Modules\X215\Ui\SignaturePad::class)->name('x-215.signature-pad');
    Route::get('/x-215/comment-thread', \App\Modules\X215\Ui\CommentThread::class)->name('x-215.comment-thread');
});

