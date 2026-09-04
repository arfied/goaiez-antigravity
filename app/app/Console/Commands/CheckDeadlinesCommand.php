<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckDeadlinesCommand extends Command
{
    protected $signature = 'disputes:check-deadlines';
    protected $description = 'Raise disputes to human if deadline is within 48 hours';

    public function handle()
    {
        $now = Carbon::now();
        $threshold = $now->copy()->addHours(48);

        $disputes = DB::table('disputes')
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', $threshold)
            ->where('status', '!=', 'resolved')
            ->get();

        foreach ($disputes as $dispute) {
            $exists = DB::table('dispute_audits')
                ->where('dispute_id', $dispute->id)
                ->where('action', 'raised_to_human')
                ->exists();

            if (!$exists) {
                DB::table('dispute_audits')->insert([
                    'business_id' => $dispute->business_id,
                    'dispute_id' => $dispute->id,
                    'action' => 'raised_to_human',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                
                DB::table('disputes')->where('id', $dispute->id)->update(['status' => 'raised']);
            }
        }
    }
}
