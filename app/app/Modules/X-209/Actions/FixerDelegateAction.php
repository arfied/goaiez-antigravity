<?php

declare(strict_types=1);

namespace App\Modules\X209\Actions;

use App\Modules\X209\Events\FixerEscalated;
use App\Modules\X209\Models\FixerCommand;
use Illuminate\Support\Facades\Event;

final class FixerDelegateAction
{
    public function delegate(int $businessId, int $commandId, string $reason): FixerCommand
    {
        $command = FixerCommand::where('business_id', $businessId)->findOrFail($commandId);
        $command->update(['status' => 'escalated']);

        Event::dispatch(new FixerEscalated($businessId, $command->id, $reason));

        return $command;
    }
}
