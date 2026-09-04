<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-135')->group(function () {
    Route::get('/x-135/research-dossier-per', \App\Modules\X135\Ui\ResearchDossierPer::class)->name('x-135.research-dossier-per');
});

