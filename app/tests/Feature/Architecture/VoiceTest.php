<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyVoiceWorker;
use Illuminate\Support\Facades\Route;

/*
 * The voice brain API's two standing rules (AI receptionist plan, 2026-10-05). Many docblocks in app/Services/Voice cite a
 * VoiceTest that was never written; this is it, starting with the live-call API.
 */

test('every voice brain route is signed by the voice worker', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r): bool => str_starts_with($r->uri(), 'api/voice/'));

    // Positive control: the call-start route exists, so an empty list cannot pass this test.
    expect($routes->map->uri()->all())->toContain('api/voice/v1/calls');

    $unsigned = $routes->reject(fn ($r): bool => in_array(VerifyVoiceWorker::class, $r->gatherMiddleware(), true))->map->uri()->values()->all();
    expect($unsigned)->toBe([], 'A voice brain route answers without the worker\'s signature');
});

test('no voice brain controller reads the tenant from the request', function (): void {
    $pattern = '/(?:input|get|query|post|json|integer|string)\(\s*[\'"]business_id[\'"]|request\(\s*[\'"]business_id[\'"]|\[\s*[\'"]business_id[\'"]\s*\]/';

    // Positive control: the pattern catches the shapes it exists for.
    expect(preg_match($pattern, '$request->input(\'business_id\')'))->toBe(1)
        ->and(preg_match($pattern, '$data["business_id"]'))->toBe(1);

    $offences = [];
    foreach (glob(app_path('Http/Controllers/Voice/Live/*.php')) as $file) {
        if (preg_match($pattern, (string) file_get_contents($file)) === 1) {
            $offences[] = basename($file);
        }
    }
    expect(glob(app_path('Http/Controllers/Voice/Live/*.php')))->not->toBe([])
        ->and($offences)->toBe([], 'A voice brain controller takes the tenant from what the worker sent; derive it from the signed call token');
});
