<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-140')->group(function () {
    Route::get('/proposed-pages', \App\Modules\X140\Ui\ProposedPagesView::class)->name('x-140.proposed-pages');
});

