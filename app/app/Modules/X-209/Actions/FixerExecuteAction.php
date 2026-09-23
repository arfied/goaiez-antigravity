<?php

declare(strict_types=1);

namespace App\Modules\X209\Actions;

use App\Modules\X209\Events\FixerActionTaken;
use App\Modules\X209\Models\FixerCommand;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class FixerExecuteAction
{
    private FixerApproveAction $approveAction;

    public function __construct(FixerApproveAction $approveAction)
    {
        $this->approveAction = $approveAction;
    }

    public function execute(int $businessId, int $commandId): FixerCommand
    {
        $command = FixerCommand::where('business_id', $businessId)->findOrFail($commandId);

        if ($command->status !== 'pending_approval') {
            throw new InvalidArgumentException('Command is not pending approval.');
        }

        $command->update([
            'consent_decision' => 'approved',
            'outbound_message_id' => 'msg_fixer_'.$command->id,
            'status' => 'executed',
        ]);

        Event::dispatch(new FixerActionTaken($businessId, $command->id, $command->parsed_intent, $command->outbound_message_id));

        $this->approveAction->approve($businessId, $command->parsed_intent);

        return $command;
    }
}
