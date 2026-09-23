<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CheckDeadlinesCommand extends Command
{
    protected $signature = 'disputes:check-deadlines';

    protected $description = 'Raise disputes to human if deadline is within 48 hours';

    public function handle()
    {
        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users): void {
                foreach ($users as $user) {
                    $this->sweepOwner((int) $user->getKey());
                }
            });

        Tenancy::forgetAll();
    }

    private function sweepOwner(int $userId): void
    {
        Tenancy::setUser($userId);

        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        foreach ($businessIds as $businessId) {
            $this->sweepBusiness((int) $businessId);
        }
    }

    private function sweepBusiness(int $businessId): void
    {
        Tenancy::set($businessId);

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

            if (! $exists) {
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
