<?php

declare(strict_types=1);
use Illuminate\Support\Facades\File;

afterEach(function () {
    $out = storage_path('framework/testing/ws-templates-'.getmypid());
    if (is_dir($out)) {
        File::deleteDirectory($out);
    }
});

it('renders all templates and writes manifest', function () {
    $out = storage_path('framework/testing/ws-templates-'.getmypid());

    $this->artisan('webstudio:render-templates', ['--out' => $out])
        ->assertSuccessful();

    $files = glob("$out/*/index.html");
    expect($files)->toHaveCount(21);

    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)->toContain('<!doctype html>');
        expect($content)->toContain('<style>');
        expect(str_contains($content, 'Calder &amp; Sons Plumbing') || str_contains($content, 'Calder & Sons Plumbing'))->toBeTrue();
        expect($content)->toContain('--color-primary:');
    }

    $manifestPath = "$out/templates.json";
    expect($manifestPath)->toBeFile();

    $manifest = json_decode(file_get_contents($manifestPath), true);
    expect($manifest)->toHaveCount(21);

    foreach ($manifest as $id => $data) {
        expect($data)->toHaveKey('label');
        expect($data)->toHaveKey('bytes');
        expect($data['bytes'])->toBeGreaterThan(4000);
    }

    expect(file_get_contents("$out/trades-pro/index.html"))->toContain('Emergency Repairs');
    expect(file_get_contents("$out/maker-market/index.html"))->toContain('Starter kit');
});
