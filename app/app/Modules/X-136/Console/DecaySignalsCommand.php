<?php

declare(strict_types=1);

namespace App\Modules\X136\Console;

use App\Models\Business;
use App\Models\User;
use App\Modules\X136\Actions\SignalListAction;
use App\Modules\X136\Models\DecayModel;
use App\Modules\X136\Models\SignalScore;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

final class DecaySignalsCommand extends Command
{
    protected $signature = 'x136:decay-signals';

    protected $description = 'Mark cooling signals older than their type\'s half-life as decayed, one prospect at a time';

    public function __construct(private readonly SignalListAction $signalListAction)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $decayed = 0;
        User::query()->select('id')->orderBy('id')->chunkById(200, function (Collection $users) use (&$decayed): void {
            foreach ($users as $user) {
                $decayed += $this->sweepOwner((int) $user->getKey());
            }
        });
        Tenancy::forgetAll();
        $this->info($decayed === 0 ? 'No signal has cooled past its half-life.' : "Marked {$decayed} prospect(s) decayed.");

        return self::SUCCESS;
    }

    private function sweepOwner(int $userId): int
    {
        Tenancy::setUser($userId);
        $businesses = Business::withoutGlobalScopes()->where('owner_user_id', $userId)->get();
        $decayed = 0;
        foreach ($businesses as $business) {
            $decayed += Tenancy::actingAs((int) $business->id, function () use ($business): int {
                $count = 0;
                foreach (DecayModel::where('business_id', $business->id)->get() as $model) {
                    $cutoff = now()->subDays((int) $model->half_life_days);
                    $prospects = SignalScore::query()
                        ->where('signal_scores.business_id', $business->id)
                        ->where('signal_scores.cooling_status', 'cooling')
                        ->where('signal_scores.updated_at', '<', $cutoff)
                        ->join('signals', 'signals.id', '=', 'signal_scores.signal_id')
                        ->where('signals.signal_type', $model->signal_type)
                        ->distinct()
                        ->pluck('signal_scores.prospect_identifier');
                    foreach ($prospects as $prospect) {
                        $this->signalListAction->markDecayed((int) $business->id, (string) $prospect, (int) $model->half_life_days);
                        $count++;
                    }
                }

                return $count;
            });
        }

        return $decayed;
    }
}
