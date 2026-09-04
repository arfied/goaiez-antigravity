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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-218')->group(function () {
    Route::get('/x-218/discoveryboard', \App\Modules\X218\Ui\DiscoveryBoard::class)->name('x-218.discovery-board');
    Route::get('/x-218/dealtracker', \App\Modules\X218\Ui\DealTracker::class)->name('x-218.deal-tracker');
    Route::get('/x-218/deliverableproof', \App\Modules\X218\Ui\DeliverableProof::class)->name('x-218.deliverable-proof');
});

