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
