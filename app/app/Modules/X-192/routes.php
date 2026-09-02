<?php

use App\Modules\X192\Ui\MembershipsList;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/memberships', MembershipsList::class)->name('x192.memberships');
});
