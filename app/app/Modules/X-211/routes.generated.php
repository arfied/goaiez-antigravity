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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-211')->group(function () {
    Route::get('/x-211/ageing-by-reason', \App\Modules\X211\Ui\AgeingByReason::class)->name('x-211.ageing-by-reason');
    Route::get('/x-211/invoice-thread-beside', \App\Modules\X211\Ui\InvoiceThreadBeside::class)->name('x-211.invoice-thread-beside');
    Route::get('/x-211/paymentplan-builder', \App\Modules\X211\Ui\PaymentplanBuilder::class)->name('x-211.paymentplan-builder');
    Route::get('/x-211/collections-package-preview', \App\Modules\X211\Ui\CollectionsPackagePreview::class)->name('x-211.collections-package-preview');
});

