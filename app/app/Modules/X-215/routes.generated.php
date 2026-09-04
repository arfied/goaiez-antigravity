<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X215\Ui\CommentThread;
use App\Modules\X215\Ui\DocumentStatus;
use App\Modules\X215\Ui\SignaturePad;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-215')->group(function () {
    Route::get('/x-215/document-status', DocumentStatus::class)->name('x-215.document-status');
    Route::get('/x-215/signature-pad', SignaturePad::class)->name('x-215.signature-pad');
    Route::get('/x-215/comment-thread', CommentThread::class)->name('x-215.comment-thread');
});
