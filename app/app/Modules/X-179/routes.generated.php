<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-179')->group(function () {
    Route::get('/prospecttenantfacing-top3-preview', \App\Modules\X179\Ui\ProspecttenantfacingTop3Preview::class)->name('x-179.prospecttenantfacing-top3-preview.admin');
    Route::get('/match-scores', \App\Modules\X179\Ui\MatchScores::class)->name('x-179.match-scores.admin');
});

