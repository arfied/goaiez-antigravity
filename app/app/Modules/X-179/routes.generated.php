<?php

declare(strict_types=1);

use App\Modules\X179\Ui\MatchScores;
use App\Modules\X179\Ui\ProspecttenantfacingTop3Preview;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-179')->group(function () {
    Route::get('/prospecttenantfacing-top3-preview/{prospectId}', ProspecttenantfacingTop3Preview::class)->name('x-179.prospecttenantfacing-top3-preview');
    Route::get('/match-scores/{prospectId}', MatchScores::class)->name('x-179.match-scores');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-179')->group(function () {
    Route::get('/prospecttenantfacing-top3-preview/{prospectId}', ProspecttenantfacingTop3Preview::class)->name('x-179.prospecttenantfacing-top3-preview.admin');
    Route::get('/match-scores/{prospectId}', MatchScores::class)->name('x-179.match-scores.admin');
});
