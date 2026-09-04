<?php

declare(strict_types=1);

use App\Modules\X176\Ui\SeoTabWebsite;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-176')->group(function () {
    Route::get('/seo-tab-website', SeoTabWebsite::class)->name('x-176.seo-tab-website');
});
