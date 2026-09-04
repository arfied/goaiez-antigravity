<?php

declare(strict_types=1);

use App\Modules\X215\Ui\CommentThread;
use App\Modules\X215\Ui\DocumentStatus;
use App\Modules\X215\Ui\SignaturePad;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-215')->group(function () {
    Route::get('/document-status', DocumentStatus::class)->name('x-215.document-status');
    Route::get('/signature-pad', SignaturePad::class)->name('x-215.signature-pad');
    Route::get('/comment-thread', CommentThread::class)->name('x-215.comment-thread');
});
