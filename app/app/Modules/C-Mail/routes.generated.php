<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-mail')->group(function () {
    Route::get('/c-mail/dns-card', \App\Modules\CMail\Ui\DnsCard::class)->name('c-mail.dns-card');
    Route::get('/c-mail/sequence-view', \App\Modules\CMail\Ui\SequenceView::class)->name('c-mail.sequence-view');
    Route::get('/c-mail/warmup-calendars-per', \App\Modules\CMail\Ui\WarmupCalendarsPer::class)->name('c-mail.warmup-calendars-per');
    Route::get('/c-mail/complaintbounce-board', \App\Modules\CMail\Ui\ComplaintbounceBoard::class)->name('c-mail.complaintbounce-board');
});

