<?php

declare(strict_types=1);

namespace App\Modules\X111\Actions;

use App\Modules\X111\Models\OperatorAlert;

final class ResolveAlertAction
{
    public function handle(OperatorAlert $alert): void
    {
        $alert->update(['status' => 'resolved']);
    }
}
