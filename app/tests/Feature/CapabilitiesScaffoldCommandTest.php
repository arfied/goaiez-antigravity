<?php

declare(strict_types=1);

use App\Console\Commands\CapabilitiesScaffoldCommand;

it('attributes range rows correctly', function () {
    $command = new CapabilitiesScaffoldCommand;
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('parse');
    $method->setAccessible(true);

    $tracker = <<<'TRACKER'
| **N-062…N-063** | **X-129 · X-165** | **invariants** | **prose mentions X-999 but it should get nothing** |
TRACKER;

    $result = $method->invoke($command, $tracker);

    expect($result)->toHaveKey('X-129')
        ->and($result)->toHaveKey('X-165')
        ->and($result)->not->toHaveKey('X-999')
        ->and($result['X-129'])->toHaveKey('N-062')
        ->and($result['X-129'])->toHaveKey('N-063');
});
