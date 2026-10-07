<?php

namespace Tests\Feature\Console;

use App\Models\WebstudioTemplateProject;
use Illuminate\Support\Facades\File;

afterEach(function () {
    $dir = storage_path('framework/testing/webstudio');
    if (is_dir($dir)) {
        File::deleteDirectory($dir);
    }
});

it('records an existing map and updates in place', function () {
    $dir = storage_path('framework/testing/webstudio');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $mapFile = $dir.'/map.json';

    file_put_contents($mapFile, json_encode([
        'trades-pro' => [
            'projectId' => 'proj-1',
            'label' => 'Trades Pro',
            'imported_at' => now()->toIso8601String(),
        ],
        'calm-spa' => [
            'projectId' => 'proj-2',
            'label' => 'Calm Spa',
            'imported_at' => now()->toIso8601String(),
        ],
    ]));

    $this->artisan('webstudio:import-templates', ['--map' => $mapFile])
        ->expectsOutput('recorded 2 template projects')
        ->assertExitCode(0);

    expect(WebstudioTemplateProject::count())->toBe(2);
    $trades = WebstudioTemplateProject::where('template_id', 'trades-pro')->first();
    expect($trades->project_id)->toBe('proj-1');
    expect($trades->label)->toBe('Trades Pro');
    expect($trades->builder_origin)->toBe(config('site_clone.builder_origin'));

    // update in place
    file_put_contents($mapFile, json_encode([
        'trades-pro' => [
            'projectId' => 'proj-1-updated',
            'label' => 'Trades Pro',
            'imported_at' => now()->toIso8601String(),
        ],
        'calm-spa' => [
            'projectId' => 'proj-2',
            'label' => 'Calm Spa',
            'imported_at' => now()->toIso8601String(),
        ],
    ]));

    $this->artisan('webstudio:import-templates', ['--map' => $mapFile])
        ->expectsOutput('recorded 2 template projects')
        ->assertExitCode(0);

    expect(WebstudioTemplateProject::count())->toBe(2);
    $trades = WebstudioTemplateProject::where('template_id', 'trades-pro')->first();
    expect($trades->project_id)->toBe('proj-1-updated');
});

it('refuses unknown template ids', function () {
    $dir = storage_path('framework/testing/webstudio');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $mapFile = $dir.'/map.json';

    file_put_contents($mapFile, json_encode([
        'no-such-template' => [
            'projectId' => 'proj-unknown',
            'label' => 'Unknown',
            'imported_at' => now()->toIso8601String(),
        ],
    ]));

    $this->artisan('webstudio:import-templates', ['--map' => $mapFile])
        ->expectsOutput('Unknown template ids: no-such-template')
        ->assertExitCode(1);

    expect(WebstudioTemplateProject::count())->toBe(0);
});

it('refuses to run without credential', function () {
    config(['credentials.webstudio_auth_secret' => null]);
    $this->artisan('webstudio:import-templates')
        ->expectsOutput('Missing webstudio_auth_secret credential')
        ->assertExitCode(1);
});
