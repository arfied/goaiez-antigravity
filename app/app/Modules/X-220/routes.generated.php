<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X220\Ui\EvalReport;
use App\Modules\X220\Ui\PromptHistory;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-220')->group(function () {
    Route::get('/x-220/prompt-history', PromptHistory::class)->name('x-220.prompt-history');
    Route::get('/x-220/eval-report', EvalReport::class)->name('x-220.eval-report');
});
