<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X178\Ui\SiteEditorAssistant;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-178')->group(function () {
    Route::get('/x-178/site-editor-assistant', SiteEditorAssistant::class)->name('x-178.site-editor-assistant');
});
