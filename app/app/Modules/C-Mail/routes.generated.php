<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/c-mail')->group(function () {
    Route::get('/dns-card', \App\Modules\CMail\Ui\DnsCard::class)->name('c-mail.dns-card');
    Route::get('/sequence-view', \App\Modules\CMail\Ui\SequenceView::class)->name('c-mail.sequence-view');
    Route::get('/warmup-calendars-per', \App\Modules\CMail\Ui\WarmupCalendarsPer::class)->name('c-mail.warmup-calendars-per');
    Route::get('/complaintbounce-board', \App\Modules\CMail\Ui\ComplaintbounceBoard::class)->name('c-mail.complaintbounce-board');
});

