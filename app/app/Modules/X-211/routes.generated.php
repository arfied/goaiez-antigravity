<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X211\Ui\AgeingByReason;
use App\Modules\X211\Ui\CollectionsPackagePreview;
use App\Modules\X211\Ui\InvoiceThreadBeside;
use App\Modules\X211\Ui\PaymentplanBuilder;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-211')->group(function () {
    Route::get('/x-211/ageing-by-reason', AgeingByReason::class)->name('x-211.ageing-by-reason');
    Route::get('/x-211/invoice-thread-beside', InvoiceThreadBeside::class)->name('x-211.invoice-thread-beside');
    Route::get('/x-211/paymentplan-builder', PaymentplanBuilder::class)->name('x-211.paymentplan-builder');
    Route::get('/x-211/collections-package-preview', CollectionsPackagePreview::class)->name('x-211.collections-package-preview');
});
