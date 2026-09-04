<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-217')->group(function () {
    Route::get('/recruitpipeline', \App\Modules\X217\Ui\RecruitPipeline::class)->name('x-217.recruit-pipeline');
    Route::get('/offercomposer', \App\Modules\X217\Ui\OfferComposer::class)->name('x-217.offer-composer');
});

