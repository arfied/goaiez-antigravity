<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X144\Ui\QuestionList;
use App\Modules\X144\Ui\TenantZerosOwn;
use App\Modules\X144\Ui\VisibilityTile;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-144')->group(function () {
    Route::get('/x-144/visibility-tile', VisibilityTile::class)->name('x-144.visibility-tile');
    Route::get('/x-144/question-list', QuestionList::class)->name('x-144.question-list');
    Route::get('/x-144/tenant-zeros-own', TenantZerosOwn::class)->name('x-144.tenant-zeros-own');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-144')->group(function () {
    Route::get('/x-144/visibility-tile', VisibilityTile::class)->name('x-144.visibility-tile');
    Route::get('/x-144/question-list', QuestionList::class)->name('x-144.question-list');
    Route::get('/x-144/tenant-zeros-own', TenantZerosOwn::class)->name('x-144.tenant-zeros-own');
});
