<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-144')->group(function () {
    Route::get('/visibility-tile', \App\Modules\X144\Ui\VisibilityTile::class)->name('x-144.visibility-tile');
    Route::get('/question-list', \App\Modules\X144\Ui\QuestionList::class)->name('x-144.question-list');
    Route::get('/tenant-zeros-own', \App\Modules\X144\Ui\TenantZerosOwn::class)->name('x-144.tenant-zeros-own');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-144')->group(function () {
    Route::get('/visibility-tile', \App\Modules\X144\Ui\VisibilityTile::class)->name('x-144.visibility-tile.admin');
    Route::get('/question-list', \App\Modules\X144\Ui\QuestionList::class)->name('x-144.question-list.admin');
    Route::get('/tenant-zeros-own', \App\Modules\X144\Ui\TenantZerosOwn::class)->name('x-144.tenant-zeros-own.admin');
});

