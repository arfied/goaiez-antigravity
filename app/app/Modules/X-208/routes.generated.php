<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X208\Ui\Cost;
use App\Modules\X208\Ui\PiecePreview;
use App\Modules\X208\Ui\SendRecord;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-208')->group(function () {
    Route::get('/x-208/piece-preview', PiecePreview::class)->name('x-208.piece-preview');
    Route::get('/x-208/cost', Cost::class)->name('x-208.cost');
    Route::get('/x-208/send-record', SendRecord::class)->name('x-208.send-record');
});
