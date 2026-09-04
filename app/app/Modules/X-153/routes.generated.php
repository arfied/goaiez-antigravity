<?php

declare(strict_types=1);

use App\Modules\X153\Ui\AlertReplyBy;
use App\Modules\X153\Ui\AlertRosterScreen;
use App\Modules\X153\Ui\ClaimexpiryRate;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-153')->group(function () {
    Route::get('/alert-reply-by', AlertReplyBy::class)->name('x-153.alert-reply-by');
    Route::get('/alert-roster-screen', AlertRosterScreen::class)->name('x-153.alert-roster-screen');
    Route::get('/claimexpiry-rate', ClaimexpiryRate::class)->name('x-153.claimexpiry-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-153')->group(function () {
    Route::get('/alert-reply-by', AlertReplyBy::class)->name('x-153.alert-reply-by.admin');
    Route::get('/alert-roster-screen', AlertRosterScreen::class)->name('x-153.alert-roster-screen.admin');
    Route::get('/claimexpiry-rate', ClaimexpiryRate::class)->name('x-153.claimexpiry-rate.admin');
});
