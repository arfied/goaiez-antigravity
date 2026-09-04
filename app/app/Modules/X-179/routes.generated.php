<?php

declare(strict_types=1);

use App\Modules\X179\Ui\MatchScores;
use App\Modules\X179\Ui\ProspecttenantfacingTop3Preview;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-179')->group(function () {
    Route::get('/x-179/prospecttenantfacing-top3-preview', ProspecttenantfacingTop3Preview::class)->name('x-179.prospecttenantfacing-top3-preview');
    Route::get('/x-179/match-scores', MatchScores::class)->name('x-179.match-scores');
});
