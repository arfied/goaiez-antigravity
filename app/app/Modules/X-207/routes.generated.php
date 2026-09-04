<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-207')->group(function () {
    Route::get('/x-207/one-confirmonce-toggle', \App\Modules\X207\Ui\OneConfirmonceToggle::class)->name('x-207.one-confirmonce-toggle');
    Route::get('/x-207/promptcopy-editor', \App\Modules\X207\Ui\PromptcopyEditor::class)->name('x-207.promptcopy-editor');
    Route::get('/x-207/retirement-reasons', \App\Modules\X207\Ui\RetirementReasons::class)->name('x-207.retirement-reasons');
    Route::get('/x-207/perplatform-delivery-health', \App\Modules\X207\Ui\PerplatformDeliveryHealth::class)->name('x-207.perplatform-delivery-health');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-207')->group(function () {
    Route::get('/x-207/one-confirmonce-toggle', \App\Modules\X207\Ui\OneConfirmonceToggle::class)->name('x-207.one-confirmonce-toggle');
    Route::get('/x-207/promptcopy-editor', \App\Modules\X207\Ui\PromptcopyEditor::class)->name('x-207.promptcopy-editor');
    Route::get('/x-207/retirement-reasons', \App\Modules\X207\Ui\RetirementReasons::class)->name('x-207.retirement-reasons');
    Route::get('/x-207/perplatform-delivery-health', \App\Modules\X207\Ui\PerplatformDeliveryHealth::class)->name('x-207.perplatform-delivery-health');
});

