<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-220')->group(function () {
    Route::get('/prompt-history', \App\Modules\X220\Ui\PromptHistory::class)->name('x-220.prompt-history');
    Route::get('/eval-report', \App\Modules\X220\Ui\EvalReport::class)->name('x-220.eval-report');
});

