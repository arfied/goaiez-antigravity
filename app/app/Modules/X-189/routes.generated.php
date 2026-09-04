<?php

declare(strict_types=1);

use App\Modules\X189\Ui\BrandCardEditor;
use App\Modules\X189\Ui\PreviewPerDestination;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-189')->group(function () {
    Route::get('/brand-card-editor', BrandCardEditor::class)->name('x-189.brand-card-editor');
    Route::get('/preview-per-destination', PreviewPerDestination::class)->name('x-189.preview-per-destination');
});
