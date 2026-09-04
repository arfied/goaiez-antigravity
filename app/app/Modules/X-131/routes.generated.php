<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-131')->group(function () {
    Route::get('/interest-tags', \App\Modules\X131\Ui\InterestTagsView::class)->name('x-131.interest-tags');
});

