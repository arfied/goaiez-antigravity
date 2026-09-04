<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-196')->group(function () {
    Route::get('/x-196/extension-popup', \App\Modules\X196\Ui\ExtensionPopup::class)->name('x-196.extension-popup');
});

