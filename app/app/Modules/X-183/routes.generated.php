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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-183')->group(function () {
    Route::get('/x-183/draft-review', \App\Modules\X183\Ui\DraftReview::class)->name('x-183.draft-review');
    Route::get('/x-183/gate-rejection-reasons', \App\Modules\X183\Ui\GateRejectionReasons::class)->name('x-183.gate-rejection-reasons');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-183')->group(function () {
    Route::get('/x-183/draft-review', \App\Modules\X183\Ui\DraftReview::class)->name('x-183.draft-review');
    Route::get('/x-183/gate-rejection-reasons', \App\Modules\X183\Ui\GateRejectionReasons::class)->name('x-183.gate-rejection-reasons');
});

