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

        Tenancy::setUser($user->id);
        $business = Business::withoutGlobalScopes()->where('owner_user_id', $user->id)->firstOrFail();

        $only = $this->option('only');
        $onlyModules = $only ? explode(',', $only) : null;

        Tenancy::set($business->id); // PB-207: a console command establishes its own tenant — Context does not propagate here (production, 2026-09-16).

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
        Tenancy::forgetUser();

        return 0;
    }
}
