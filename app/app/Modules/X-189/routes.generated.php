<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X189\Ui\BrandCardEditor;
use App\Modules\X189\Ui\PreviewPerDestination;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-189')->group(function () {
    Route::get('/x-189/brand-card-editor', BrandCardEditor::class)->name('x-189.brand-card-editor');
    Route::get('/x-189/preview-per-destination', PreviewPerDestination::class)->name('x-189.preview-per-destination');
});
