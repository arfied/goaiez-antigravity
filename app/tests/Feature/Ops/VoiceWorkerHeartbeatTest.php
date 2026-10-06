<?php

declare(strict_types=1);

use App\Enums\OperatorAlertKind;
use App\Models\OperatorAlert;
use App\Services\Config\DefaultsRegistry;
use App\Services\Ops\PlatformHealth;
use App\Services\Ops\PlatformHealthChecks;

/*
 * The voice worker's silence pages the operator — only while the AI receptionist is switched on (plan 2026-10-05).
 */

function voiceWorkerAlerts(): int
{
    return OperatorAlert::query()
        ->where('kind', OperatorAlertKind::HeartbeatSilent->value)
        ->where('subject', PlatformHealthChecks::VOICE_WORKER)
        ->count();
}

test('a silent or absent voice worker pages nobody while the receptionist is switched off', function (): void {
    expect(app(PlatformHealthChecks::class)->sweepVoiceWorker())->toBe(0)
        ->and(voiceWorkerAlerts())->toBe(0);
});

test('with the receptionist switched on, a worker that has never reported pages the operator', function (): void {
    app(DefaultsRegistry::class)->set('voice.live_agent.enabled', true, 'test');

    expect(app(PlatformHealthChecks::class)->sweepVoiceWorker())->toBe(1)
        ->and(voiceWorkerAlerts())->toBe(1);

    $alert = OperatorAlert::query()->where('subject', PlatformHealthChecks::VOICE_WORKER)->sole();
    expect($alert->summary)->toContain('has never reported');
});

test('a worker that reported recently raises nothing, and one silent past the threshold pages, through the heartbeat sweep', function (): void {
    app(DefaultsRegistry::class)->set('voice.live_agent.enabled', true, 'test');
    app(PlatformHealth::class)->beat(PlatformHealthChecks::VOICE_WORKER);

    expect(app(PlatformHealthChecks::class)->sweepVoiceWorker())->toBe(0);

    $this->travel(4)->minutes();

    // Through sweepHeartbeats(), which is what the web process runs — the voice worker rides it.
    app(PlatformHealthChecks::class)->sweepHeartbeats();

    expect(voiceWorkerAlerts())->toBe(1);
    $alert = OperatorAlert::query()->where('subject', PlatformHealthChecks::VOICE_WORKER)->sole();
    expect($alert->summary)->toContain('has not reported for 4 minutes');
});
