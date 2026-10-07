<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('site_clone_jobs has row-level security enabled AND forced, and a policy named tenant_isolation', function () {
    $cloneJobs = DB::selectOne("select relrowsecurity, relforcerowsecurity from pg_class where relname = 'site_clone_jobs'");
    expect($cloneJobs->relrowsecurity)->toBeTrue()
        ->and($cloneJobs->relforcerowsecurity)->toBeTrue();

    $cloneJobsPolicies = DB::select("select policyname from pg_policies where tablename = 'site_clone_jobs'");
    $policyNames = array_column($cloneJobsPolicies, 'policyname');
    expect($policyNames)->toContain('tenant_isolation');

    // positive control
    $inventoryPages = DB::selectOne("select relrowsecurity, relforcerowsecurity from pg_class where relname = 'site_inventory_pages'");
    expect($inventoryPages->relrowsecurity)->toBeTrue()
        ->and($inventoryPages->relforcerowsecurity)->toBeTrue();
});

it('has the two partial unique indexes', function () {
    $indexes = DB::select("select indexname from pg_indexes where tablename = 'site_clone_jobs'");
    $indexNames = array_column($indexes, 'indexname');

    expect($indexNames)->toContain('site_clone_jobs_one_active_per_business')
        ->toContain('site_clone_jobs_one_active_per_user');
});

it('route site.clone carries auth and tenant.role', function () {
    $route = Route::getRoutes()->getByName('site.clone');
    expect($route->gatherMiddleware())->toContain('auth', 'tenant.role');
});

it('view does not leak internal details', function () {
    $view = file_get_contents(resource_path('views/livewire/site/clone.blade.php'));
    expect($view)->not->toContain('internal_error')
        ->and($view)->not->toContain('work_dir')
        ->and($view)->not->toContain('pid')
        ->and($view)->not->toContain('editor_token');

    // positive control
    $model = file_get_contents(database_path('migrations/2026_10_07_120000_create_site_clone_jobs_table.php'));
    expect($model)->toContain('internal_error')
        ->and($model)->toContain('work_dir')
        ->and($model)->toContain('pid')
        ->and($model)->toContain('editor_token');
});

it('job file contains no public_html or shell evasion', function () {
    $job = file_get_contents(app_path('Jobs/SiteClone/RunSiteCloneJob.php'));
    expect($job)->not->toContain('public_html')
        ->and($job)->not->toContain('eval(')
        ->and($job)->not->toContain('shell_exec(')
        ->and($job)->not->toContain('exec(')
        ->and($job)->not->toContain('passthru(')
        ->and($job)->not->toContain('proc_open(')
        ->and($job)->toContain('new Process(');
});

it('routes/console.php schedules queue:work with clone queue', function () {
    $console = file_get_contents(base_path('routes/console.php'));
    expect($console)->toContain('queue:work --queue=clone');
});

it('view contains no authToken', function () {
    $view = file_get_contents(resource_path('views/livewire/site/clone.blade.php'));
    expect($view)->not->toContain('authToken');
});

it('job file passes WS_AUTH_SECRET safely', function () {
    $job = file_get_contents(app_path('Jobs/SiteClone/RunSiteCloneJob.php'));
    // Make sure it doesn't just blindly assign WS_AUTH_SECRET
    $lines = explode("\n", $job);
    $foundEnvAuthSecret = false;
    $foundPlatformCredentialsHas = false;
    $insideHasBlock = false;

    foreach ($lines as $line) {
        if (str_contains($line, "PlatformCredentials::has('webstudio_auth_secret')")) {
            $insideHasBlock = true;
            $foundPlatformCredentialsHas = true;
        }
        if (str_contains($line, "['WS_AUTH_SECRET']")) {
            if (! $insideHasBlock) {
                // found outside the has block!
                $this->fail('WS_AUTH_SECRET is set outside the has("webstudio_auth_secret") block.');
            }
            $foundEnvAuthSecret = true;
        }
        if ($insideHasBlock && str_contains($line, '}')) {
            $insideHasBlock = false;
        }
    }

    expect($foundEnvAuthSecret)->toBeTrue()
        ->and($foundPlatformCredentialsHas)->toBeTrue();
});
