<?php

declare(strict_types=1);

use App\Modules\X109\Ui\ManualQueue;
use App\Modules\X109\Ui\SubmissionLog;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-109')->group(function () {
    Route::get('/x-109/submission-log', SubmissionLog::class)->name('x-109.submission-log');
    Route::get('/x-109/manual-queue', ManualQueue::class)->name('x-109.manual-queue');
});
