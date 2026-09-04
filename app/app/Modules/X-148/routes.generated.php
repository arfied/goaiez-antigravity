<?php

declare(strict_types=1);

use App\Modules\X148\Ui\RetrievalLatencyEmptyrate;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-148')->group(function () {
    Route::get('/retrieval-latency-emptyrate', RetrievalLatencyEmptyrate::class)->name('x-148.retrieval-latency-emptyrate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-148')->group(function () {
    Route::get('/retrieval-latency-emptyrate', RetrievalLatencyEmptyrate::class)->name('x-148.retrieval-latency-emptyrate.admin');
});
