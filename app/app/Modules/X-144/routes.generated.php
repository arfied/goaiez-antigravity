<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-144')->group(function () {
    Route::get('/x-144/visibility-tile', \App\Modules\X144\Ui\VisibilityTile::class)->name('x-144.visibility-tile');
    Route::get('/x-144/question-list', \App\Modules\X144\Ui\QuestionList::class)->name('x-144.question-list');
    Route::get('/x-144/tenant-zeros-own', \App\Modules\X144\Ui\TenantZerosOwn::class)->name('x-144.tenant-zeros-own');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-144')->group(function () {
    Route::get('/x-144/visibility-tile', \App\Modules\X144\Ui\VisibilityTile::class)->name('x-144.visibility-tile');
    Route::get('/x-144/question-list', \App\Modules\X144\Ui\QuestionList::class)->name('x-144.question-list');
    Route::get('/x-144/tenant-zeros-own', \App\Modules\X144\Ui\TenantZerosOwn::class)->name('x-144.tenant-zeros-own');
});

