<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-209')->group(function () {
    Route::get('/private-inbox', \App\Modules\X209\Ui\PrivateInbox::class)->name('x-209.private-inbox');
    Route::get('/onetap-approval-card', \App\Modules\X209\Ui\OnetapApprovalCard::class)->name('x-209.onetap-approval-card');
    Route::get('/ladders-own-state', \App\Modules\X209\Ui\LaddersOwnState::class)->name('x-209.ladders-own-state');
    Route::get('/never-setting', \App\Modules\X209\Ui\NeverSetting::class)->name('x-209.never-setting');
});

