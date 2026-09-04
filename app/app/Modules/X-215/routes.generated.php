<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-215')->group(function () {
    Route::get('/document-status', \App\Modules\X215\Ui\DocumentStatus::class)->name('x-215.document-status');
    Route::get('/signature-pad', \App\Modules\X215\Ui\SignaturePad::class)->name('x-215.signature-pad');
    Route::get('/comment-thread', \App\Modules\X215\Ui\CommentThread::class)->name('x-215.comment-thread');
});

