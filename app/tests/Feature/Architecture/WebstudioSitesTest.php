<?php

use Illuminate\Support\Facades\DB;

it('webstudio_sites has row-level security enabled AND forced, and a policy named tenant_isolation', function () {
    $sites = DB::selectOne("select relrowsecurity, relforcerowsecurity from pg_class where relname = 'webstudio_sites'");
    expect($sites->relrowsecurity)->toBeTrue()
        ->and($sites->relforcerowsecurity)->toBeTrue();

    $sitesPolicies = DB::select("select policyname from pg_policies where tablename = 'webstudio_sites'");
    $policyNames = array_column($sitesPolicies, 'policyname');
    expect($policyNames)->toContain('tenant_isolation');
});

it('publish job file contains no public_html or shell evasion', function () {
    $job = file_get_contents(app_path('Jobs/Webstudio/PublishWebstudioSiteJob.php'));
    expect($job)->not->toContain('public_html')
        ->and($job)->not->toContain('eval(')
        ->and($job)->not->toContain('shell_exec(')
        ->and($job)->not->toContain('exec(')
        ->and($job)->not->toContain('passthru(')
        ->and($job)->not->toContain('proc_open(')
        ->and($job)->toContain('new Process(');
});

it('share link is env-only', function () {
    $job = file_get_contents(app_path('Jobs/Webstudio/PublishWebstudioSiteJob.php'));
    expect($job)->toContain("'WS_SHARE_LINK' =>");

    $lines = explode("\n", $job);
    foreach ($lines as $i => $line) {
        if (str_contains($line, 'new Process(')) {
            expect($line)->not->toContain('WS_SHARE_LINK');
            if (isset($lines[$i + 1])) {
                expect($lines[$i + 1])->not->toContain('WS_SHARE_LINK');
            }
        }
    }
});

it('view does not leak internal details', function () {
    $view = file_get_contents(resource_path('views/livewire/site/clone.blade.php'));
    expect($view)->not->toContain('publish_error')
        ->and($view)->not->toContain('authToken');
});

it('webstudio_template_projects is platform-level: no business_id column and no row-level security', function () {
    $columns = DB::select("select column_name from information_schema.columns where table_name = 'webstudio_template_projects' and column_name = 'business_id'");
    expect(count($columns))->toBe(0);

    $table = DB::selectOne("select relrowsecurity from pg_class where relname = 'webstudio_template_projects'");
    expect($table->relrowsecurity)->toBeFalse();
});

it('the template job passes the secret and the source project env-only', function () {
    $job = file_get_contents(app_path('Jobs/Webstudio/CreateWebstudioSiteFromTemplateJob.php'));
    expect($job)->toContain("'WS_CLONE_FROM' =>")
        ->and($job)->toContain("'WS_AUTH_SECRET' =>");

    $lines = explode("\n", $job);
    foreach ($lines as $i => $line) {
        if (str_contains($line, 'new Process(')) {
            expect($line)->not->toContain('WS_');
        }
    }
});
