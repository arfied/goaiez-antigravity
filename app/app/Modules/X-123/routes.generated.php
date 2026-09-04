<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-123')->group(function () {
    Route::get('/dlq-request-inspector', \App\Modules\X123\Ui\DlqRequestInspector::class)->name('x-123.dlq-request-inspector.admin');
});

