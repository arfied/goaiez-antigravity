<?php

declare(strict_types=1);

use App\Modules\X140\Ui\ProposedPagesView;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-140')->group(function () {
    Route::get('/proposed-pages', ProposedPagesView::class)->name('x-140.proposed-pages');
});
