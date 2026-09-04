<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-109')->group(function () {
    Route::get('/x-109/submission-log', \App\Modules\X109\Ui\SubmissionLog::class)->name('x-109.submission-log');
    Route::get('/x-109/manual-queue', \App\Modules\X109\Ui\ManualQueue::class)->name('x-109.manual-queue');
});

