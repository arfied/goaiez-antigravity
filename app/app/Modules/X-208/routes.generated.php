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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-208')->group(function () {
    Route::get('/piece-preview', \App\Modules\X208\Ui\PiecePreview::class)->name('x-208.piece-preview');
    Route::get('/cost', \App\Modules\X208\Ui\Cost::class)->name('x-208.cost');
    Route::get('/send-record', \App\Modules\X208\Ui\SendRecord::class)->name('x-208.send-record');
});

