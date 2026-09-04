<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-220')->group(function () {
    Route::get('/prompt-history', \App\Modules\X220\Ui\PromptHistory::class)->name('x-220.prompt-history');
    Route::get('/eval-report', \App\Modules\X220\Ui\EvalReport::class)->name('x-220.eval-report');
});

