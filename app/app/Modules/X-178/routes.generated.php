<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-178')->group(function () {
    Route::get('/site-editor-assistant', \App\Modules\X178\Ui\SiteEditorAssistant::class)->name('x-178.site-editor-assistant');
});

