<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-142')->group(function () {
    Route::get('/x-142/connect-your-ai', \App\Modules\X142\Ui\ConnectYourAi::class)->name('x-142.connect-your-ai');
    Route::get('/x-142/webhooks', \App\Modules\X142\Ui\WebhooksView::class)->name('x-142.webhooks');
    Route::get('/x-142/mcp-token-registry', \App\Modules\X142\Ui\McpTokenRegistry::class)->name('x-142.mcp-token-registry');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-142')->group(function () {
    Route::get('/x-142/connect-your-ai', \App\Modules\X142\Ui\ConnectYourAi::class)->name('x-142.connect-your-ai');
    Route::get('/x-142/webhooks', \App\Modules\X142\Ui\WebhooksView::class)->name('x-142.webhooks');
    Route::get('/x-142/mcp-token-registry', \App\Modules\X142\Ui\McpTokenRegistry::class)->name('x-142.mcp-token-registry');
});

