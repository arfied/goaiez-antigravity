<?php

declare(strict_types=1);

use App\Modules\X155\Ui\Forms;
use App\Modules\X155\Ui\SpamRate;
use App\Modules\X155\Ui\SubmissionsThread;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-155')->group(function () {
    Route::get('/forms', Forms::class)->name('x-155.forms');
    Route::get('/submissions-thread', SubmissionsThread::class)->name('x-155.submissions-thread');
    Route::get('/spam-rate', SpamRate::class)->name('x-155.spam-rate');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-155')->group(function () {
    Route::get('/forms', Forms::class)->name('x-155.forms.admin');
    Route::get('/submissions-thread', SubmissionsThread::class)->name('x-155.submissions-thread.admin');
    Route::get('/spam-rate', SpamRate::class)->name('x-155.spam-rate.admin');
});
