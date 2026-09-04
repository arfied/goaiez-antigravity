<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X218\Ui\DealTracker;
use App\Modules\X218\Ui\DeliverableProof;
use App\Modules\X218\Ui\DiscoveryBoard;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-218')->group(function () {
    Route::get('/x-218/discoveryboard', DiscoveryBoard::class)->name('x-218.discovery-board');
    Route::get('/x-218/dealtracker', DealTracker::class)->name('x-218.deal-tracker');
    Route::get('/x-218/deliverableproof', DeliverableProof::class)->name('x-218.deliverable-proof');
});
