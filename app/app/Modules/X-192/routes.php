<?php

use Illuminate\Support\Facades\Route;
use App\Modules\X192\Ui\MembershipsList;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/memberships', MembershipsList::class)->name('x192.memberships');
});
