<?php

declare(strict_types=1);

use App\Modules\CMail\Ui\ComplaintbounceBoard;
use App\Modules\CMail\Ui\DnsCard;
use App\Modules\CMail\Ui\SequenceView;
use App\Modules\CMail\Ui\WarmupCalendarsPer;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-mail')->group(function () {
    Route::get('/dns-card', DnsCard::class)->name('c-mail.dns-card');
    Route::get('/sequence-view', SequenceView::class)->name('c-mail.sequence-view');
    Route::get('/warmup-calendars-per', WarmupCalendarsPer::class)->name('c-mail.warmup-calendars-per');
    Route::get('/complaintbounce-board', ComplaintbounceBoard::class)->name('c-mail.complaintbounce-board');
});
