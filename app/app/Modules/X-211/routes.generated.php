<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-211')->group(function () {
    Route::get('/ageing-by-reason', \App\Modules\X211\Ui\AgeingByReason::class)->name('x-211.ageing-by-reason');
    Route::get('/invoice-thread-beside', \App\Modules\X211\Ui\InvoiceThreadBeside::class)->name('x-211.invoice-thread-beside');
    Route::get('/paymentplan-builder', \App\Modules\X211\Ui\PaymentplanBuilder::class)->name('x-211.paymentplan-builder');
    Route::get('/collections-package-preview', \App\Modules\X211\Ui\CollectionsPackagePreview::class)->name('x-211.collections-package-preview');
});

