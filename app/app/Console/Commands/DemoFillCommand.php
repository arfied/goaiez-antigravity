<?php

namespace App\Console\Commands;

use App\Console\DemoFill\Registry;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Console\Command;

class DemoFillCommand extends Command
{
    protected $signature = 'demo:fill {email : the owner\'s login email} {--purge : remove this command\'s rows for that owner instead of adding them} {--only= : comma-separated module ids, e.g. X-155,X-163}';

    protected $description = 'Fill or purge demo data for an owner.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->firstOrFail();
        $business = Business::where('owner_user_id', $user->id)->firstOrFail();

        $only = $this->option('only');
        $onlyModules = $only ? explode(',', $only) : null;

        Tenancy::setUser($user->id);

        $fillers = Registry::fillers();

        foreach ($fillers as $filler) {
            $moduleId = $filler->module();
            if ($onlyModules !== null && ! in_array($moduleId, $onlyModules, true)) {
                continue;
            }

            if ($this->option('purge')) {
                $rows = $filler->purge($business);
                $this->line("{$moduleId}  -{$rows} rows");
            } else {
                $rows = $filler->fill($business);
                $this->line("{$moduleId}  +{$rows} rows");
            }
        }

        Tenancy::forget();

        return 0;
    }
}
