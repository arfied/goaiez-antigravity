<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X142\Ui\ConnectYourAi;
use App\Modules\X142\Ui\McpTokenRegistry;
use App\Modules\X142\Ui\WebhooksView;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-142')->group(function () {
    Route::get('/x-142/connect-your-ai', ConnectYourAi::class)->name('x-142.connect-your-ai');
    Route::get('/x-142/webhooks', WebhooksView::class)->name('x-142.webhooks');
    Route::get('/x-142/mcp-token-registry', McpTokenRegistry::class)->name('x-142.mcp-token-registry');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-142')->group(function () {
    Route::get('/x-142/connect-your-ai', ConnectYourAi::class)->name('x-142.connect-your-ai');
    Route::get('/x-142/webhooks', WebhooksView::class)->name('x-142.webhooks');
    Route::get('/x-142/mcp-token-registry', McpTokenRegistry::class)->name('x-142.mcp-token-registry');
});
