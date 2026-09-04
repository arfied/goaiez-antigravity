<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-196')->group(function () {
    Route::get('/extension-popup', \App\Modules\X196\Ui\ExtensionPopup::class)->name('x-196.extension-popup.admin');
});

