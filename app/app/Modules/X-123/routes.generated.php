<?php

declare(strict_types=1);

use App\Modules\X123\Ui\DlqRequestInspector;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-123')->group(function () {
    Route::get('/x-123/dlq-request-inspector', DlqRequestInspector::class)->name('x-123.dlq-request-inspector');
});
