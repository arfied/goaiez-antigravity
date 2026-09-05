<?php

declare(strict_types=1);

use App\Modules\X144\Ui\QuestionList;
use App\Modules\X144\Ui\TenantZerosOwn;
use App\Modules\X144\Ui\VisibilityTile;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-144')->group(function () {
    Route::get('/visibility-tile', VisibilityTile::class)->name('x-144.visibility-tile');
    Route::get('/question-list', QuestionList::class)->name('x-144.question-list');
    Route::get('/tenant-zeros-own', TenantZerosOwn::class)->name('x-144.tenant-zeros-own');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-144')->group(function () {
    Route::get('/visibility-tile', VisibilityTile::class)->name('x-144.visibility-tile.admin');
    Route::get('/question-list', QuestionList::class)->name('x-144.question-list.admin');
    Route::get('/tenant-zeros-own', TenantZerosOwn::class)->name('x-144.tenant-zeros-own.admin');
});
