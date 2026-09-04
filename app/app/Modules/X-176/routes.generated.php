<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-176')->group(function () {
    Route::get('/seo-tab-website', \App\Modules\X176\Ui\SeoTabWebsite::class)->name('x-176.seo-tab-website');
});

