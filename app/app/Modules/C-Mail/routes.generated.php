<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\CMail\Ui\ComplaintbounceBoard;
use App\Modules\CMail\Ui\DnsCard;
use App\Modules\CMail\Ui\SequenceView;
use App\Modules\CMail\Ui\WarmupCalendarsPer;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-mail')->group(function () {
    Route::get('/c-mail/dns-card', DnsCard::class)->name('c-mail.dns-card');
    Route::get('/c-mail/sequence-view', SequenceView::class)->name('c-mail.sequence-view');
    Route::get('/c-mail/warmup-calendars-per', WarmupCalendarsPer::class)->name('c-mail.warmup-calendars-per');
    Route::get('/c-mail/complaintbounce-board', ComplaintbounceBoard::class)->name('c-mail.complaintbounce-board');
});
