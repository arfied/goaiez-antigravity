<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-207')->group(function () {
    Route::get('/one-confirmonce-toggle', \App\Modules\X207\Ui\OneConfirmonceToggle::class)->name('x-207.one-confirmonce-toggle');
    Route::get('/promptcopy-editor', \App\Modules\X207\Ui\PromptcopyEditor::class)->name('x-207.promptcopy-editor');
    Route::get('/retirement-reasons', \App\Modules\X207\Ui\RetirementReasons::class)->name('x-207.retirement-reasons');
    Route::get('/perplatform-delivery-health', \App\Modules\X207\Ui\PerplatformDeliveryHealth::class)->name('x-207.perplatform-delivery-health');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-207')->group(function () {
    Route::get('/one-confirmonce-toggle', \App\Modules\X207\Ui\OneConfirmonceToggle::class)->name('x-207.one-confirmonce-toggle.admin');
    Route::get('/promptcopy-editor', \App\Modules\X207\Ui\PromptcopyEditor::class)->name('x-207.promptcopy-editor.admin');
    Route::get('/retirement-reasons', \App\Modules\X207\Ui\RetirementReasons::class)->name('x-207.retirement-reasons.admin');
    Route::get('/perplatform-delivery-health', \App\Modules\X207\Ui\PerplatformDeliveryHealth::class)->name('x-207.perplatform-delivery-health.admin');
});

