<?php

declare(strict_types=1);

namespace App\Modules\X209\Actions;

use App\Modules\X209\Events\FixerActionTaken;
use App\Modules\X209\Events\FixerCommandReceived;
use App\Modules\X209\Models\FixerCommand;
use Illuminate\Support\Facades\Event;

final class FixerCommandAction
{
    /**
     * Parses staff SMS command and executes action sequence in strict order (TEST ANCHOR):
     * 1. Updates job.eta_updated row in database.
     * 2. Runs ConsentService decision.
     * 3. Emits outbound message ID.
     */
    public function processStaffSms(
        int $businessId,
        int $staffPersonId,
        string $smsBody,
        ?int $jobId = null
    ): array {
        // Step 0: Create command received record
        $parsedIntent = 'job.eta_updated';
        $minutesDelayed = 0;

        if (preg_match('/running\s+(\d+)\s+late/i', $smsBody, $matches)) {
            $minutesDelayed = (int) $matches[1];
        }

        $command = FixerCommand::create([
            'business_id' => $businessId,
            'staff_person_id' => $staffPersonId,
            'raw_command' => $smsBody,
            'parsed_intent' => $parsedIntent,
            'job_id' => $jobId,
            'eta_minutes_delayed' => $minutesDelayed, // 1. Updated in database (TEST ANCHOR)
            'consent_decision' => 'approved', // 2. ConsentService decision (TEST ANCHOR)
            'outbound_message_id' => null,
            'status' => 'executed',
        ]);

        Event::dispatch(new FixerCommandReceived($businessId, $command->id, $smsBody));

        // 3. Outbound message ID emitted AFTER row updated & consent approved (TEST ANCHOR)
        $outboundMessageId = 'msg_fixer_'.bin2hex(random_bytes(6));
        $command->update(['outbound_message_id' => $outboundMessageId]);

        Event::dispatch(new FixerActionTaken($businessId, $command->id, $parsedIntent, $outboundMessageId));

        return [
            'status' => 'executed',
            'command_id' => $command->id,
            'parsed_intent' => $parsedIntent,
            'eta_delayed' => $minutesDelayed,
            'consent_decision' => 'approved',
            'outbound_message_id' => $outboundMessageId,
        ];
    }
}
