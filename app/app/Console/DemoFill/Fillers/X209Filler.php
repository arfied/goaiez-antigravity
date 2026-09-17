<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X209\Models\FixerCommand;
use App\Modules\X209\Models\FixerLadder;

class X209Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-209';
    }

    public function fill(Business $business): int
    {
        if (FixerCommand::where('business_id', $business->id)->where('raw_command', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        FixerCommand::create([
            'business_id' => $business->id,
            'staff_person_id' => 1,
            'raw_command' => 'demo·running 20 late to the Maple St job',
            'parsed_intent' => 'job.eta_updated',
            'eta_minutes_delayed' => 20,
            'status' => 'executed',
        ]);

        FixerCommand::create([
            'business_id' => $business->id,
            'staff_person_id' => 1,
            'raw_command' => 'demo·need a second pair of hands at Ridgeline',
            'parsed_intent' => 'job.help_requested',
            'eta_minutes_delayed' => 0,
            'status' => 'escalated',
        ]);

        FixerLadder::create([
            'business_id' => $business->id,
            'action_name' => 'demo·eta_update',
            'current_level' => 4,
            'success_count' => 7,
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        $deleted = 0;
        $deleted += FixerCommand::where('business_id', $business->id)->where('raw_command', 'like', self::MARKER.'%')->delete();
        $deleted += FixerLadder::where('business_id', $business->id)->where('action_name', 'like', self::MARKER.'%')->delete();

        return $deleted;
    }
}
