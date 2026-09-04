<?php

declare(strict_types=1);

use App\Modules\X135\Ui\ResearchDossierPer;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-135')->group(function () {
    Route::get('/research-dossier-per', ResearchDossierPer::class)->name('x-135.research-dossier-per.admin');
});
