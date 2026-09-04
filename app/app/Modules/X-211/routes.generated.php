<?php

declare(strict_types=1);

use App\Modules\X211\Ui\AgeingByReason;
use App\Modules\X211\Ui\CollectionsPackagePreview;
use App\Modules\X211\Ui\InvoiceThreadBeside;
use App\Modules\X211\Ui\PaymentplanBuilder;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-211')->group(function () {
    Route::get('/ageing-by-reason', AgeingByReason::class)->name('x-211.ageing-by-reason');
    Route::get('/invoice-thread-beside', InvoiceThreadBeside::class)->name('x-211.invoice-thread-beside');
    Route::get('/paymentplan-builder', PaymentplanBuilder::class)->name('x-211.paymentplan-builder');
    Route::get('/collections-package-preview', CollectionsPackagePreview::class)->name('x-211.collections-package-preview');
});
