<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\BriefCommand;
use App\Console\Commands\CapabilitiesScaffoldCommand;
use App\Console\Commands\ContextCommand;
use App\Console\Commands\DbBootstrapCommand;
use App\Console\Commands\DeployCheckCommand;
use App\Console\Commands\DoctorCommand;
use App\Console\Commands\FindCommand;
use App\Console\Commands\ImpactCommand;
use App\Console\Commands\MakeModuleCommand;
use App\Console\Commands\MapCommand;
use App\Console\Commands\ModuleDoneCommand;
use App\Console\Commands\ModuleScaffoldCommand;
use App\Console\Commands\WhyCommand;
use App\Doctor\ManifestReader;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the GOAIEZ runtime with Laravel.
 *
 * ⛔⛔⛔ WITHOUT THIS FILE, NOTHING IN THIS BUNDLE RUNS.
 *
 * Thirteen commands, seven doctor stages, a hash-chained instruction log and
 * twelve journeys — and Laravel would not have known any of it existed. The
 * install script would have run composer, migrated, bootstrapped the database,
 * and then hit `php artisan doctor` and been told there is no such command,
 * after every earlier step reported success.
 *
 * ⭐ Laravel 11+ auto-discovers commands in app/Console/Commands. Laravel 10 and
 * earlier do NOT. This provider works on both, so the bundle does not silently
 * depend on which version the live 2,786-file tree happens to be.
 *
 * ⭐⭐ It also binds ManifestReader as a SINGLETON, which is correctness rather
 * than performance: eleven classes inject it, so a fresh instance each time
 * re-parses every manifest eleven times per command — and two instances could
 * disagree mid-run if a scaffold writes between them. That is the exact drift
 * shape this programme keeps finding.
 */
final class GoaiezRuntimeServiceProvider extends ServiceProvider
{
    /** @var list<class-string> */
    private const COMMANDS = [
        DoctorCommand::class,
            \App\Console\Commands\DoctorSelfTestCommand::class,
        ModuleDoneCommand::class,
        ModuleScaffoldCommand::class,
        CapabilitiesScaffoldCommand::class,
        MakeModuleCommand::class,
        BriefCommand::class,
        MapCommand::class,
        ImpactCommand::class,
        ContextCommand::class,
        FindCommand::class,
        WhyCommand::class,
        DbBootstrapCommand::class,
        DeployCheckCommand::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ManifestReader::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(self::COMMANDS);
        }
    }
}
