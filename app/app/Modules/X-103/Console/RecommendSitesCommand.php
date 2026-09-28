<?php

declare(strict_types=1);

namespace App\Modules\X103\Console;

use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Actions\SiteRecommendAction;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

final class RecommendSitesCommand extends Command
{
    protected $signature = 'x103:recommend-sites';

    protected $description = 'Turn this week\'s measured signals into site suggestions for every business';

    public function handle(): int
    {
        $n = 0;
        $b = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$n, &$b): void {
                foreach ($users as $user) {
                    $this->processForOwner((int) $user->getKey(), $n, $b);
                }
            });

        Tenancy::forgetAll();

        $this->info("Wrote {$n} suggestion(s) across {$b} business(es).");

        return self::SUCCESS;
    }

    private function processForOwner(int $userId, int &$n, int &$b): void
    {
        Tenancy::setUser($userId);

        $businesses = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->get();

        foreach ($businesses as $business) {
            Tenancy::actingAs((int) $business->id, function () use ($business, &$n, &$b) {
                $wrote = app(SiteRecommendAction::class)->handle((int) $business->id);
                if (count($wrote) > 0) {
                    $n += count($wrote);
                    $b++;
                }
            });
        }
    }
}
