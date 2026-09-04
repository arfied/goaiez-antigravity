<?php

declare(strict_types=1);

use App\Modules\X220\Ui\EvalReport;
use App\Modules\X220\Ui\PromptHistory;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-220')->group(function () {
    Route::get('/prompt-history', PromptHistory::class)->name('x-220.prompt-history');
    Route::get('/eval-report', EvalReport::class)->name('x-220.eval-report');
});
