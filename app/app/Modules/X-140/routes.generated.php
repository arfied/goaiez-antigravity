<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X140\Ui\ProposedPagesView;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-140')->group(function () {
    Route::get('/x-140/proposed-pages', ProposedPagesView::class)->name('x-140.proposed-pages');
});
