<?php

declare(strict_types=1);

use App\Modules\X175\Ui\StafffacingAssistantPanel;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-175')->group(function () {
    Route::get('/stafffacing-assistant-panel', StafffacingAssistantPanel::class)->name('x-175.stafffacing-assistant-panel');
});
