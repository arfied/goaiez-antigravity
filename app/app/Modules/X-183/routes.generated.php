<?php

declare(strict_types=1);

use App\Modules\X183\Ui\DraftReview;
use App\Modules\X183\Ui\GateRejectionReasons;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-183')->group(function () {
    Route::get('/draft-review', DraftReview::class)->name('x-183.draft-review');
    Route::get('/gate-rejection-reasons', GateRejectionReasons::class)->name('x-183.gate-rejection-reasons');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-183')->group(function () {
    Route::get('/draft-review', DraftReview::class)->name('x-183.draft-review.admin');
    Route::get('/gate-rejection-reasons', GateRejectionReasons::class)->name('x-183.gate-rejection-reasons.admin');
});
