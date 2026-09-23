<?php

declare(strict_types=1);

use App\Modules\X209\Ui\LaddersOwnState;
use App\Modules\X209\Ui\OnetapApprovalCard;
use App\Modules\X209\Ui\PrivateInbox;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-209')->group(function () {
    Route::get('/private-inbox', PrivateInbox::class)->name('x-209.private-inbox');
    Route::get('/onetap-approval-card', OnetapApprovalCard::class)->name('x-209.onetap-approval-card');
    Route::get('/ladders-own-state', LaddersOwnState::class)->name('x-209.ladders-own-state');
});
