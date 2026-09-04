<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X202\Ui\AuditExport;
use App\Modules\X202\Ui\Item;
use App\Modules\X202\Ui\Mobile;
use App\Modules\X202\Ui\Queue;
use App\Modules\X202\Ui\Slack;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-202')->group(function () {
    Route::get('/x-202/queue', Queue::class)->name('x-202.queue');
    Route::get('/x-202/item', Item::class)->name('x-202.item');
    Route::get('/x-202/audit-export', AuditExport::class)->name('x-202.audit-export');
    Route::get('/x-202/mobile', Mobile::class)->name('x-202.mobile');
    Route::get('/x-202/slack', Slack::class)->name('x-202.slack');
});
