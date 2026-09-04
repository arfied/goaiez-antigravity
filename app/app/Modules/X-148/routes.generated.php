<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-148')->group(function () {
    Route::get('/x-148/retrieval-latency-emptyrate', \App\Modules\X148\Ui\RetrievalLatencyEmptyrate::class)->name('x-148.retrieval-latency-emptyrate');
});

