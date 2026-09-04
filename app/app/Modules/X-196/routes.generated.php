<?php

declare(strict_types=1);

use App\Modules\X196\Ui\ExtensionPopup;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-196')->group(function () {
    Route::get('/x-196/extension-popup', ExtensionPopup::class)->name('x-196.extension-popup');
});
