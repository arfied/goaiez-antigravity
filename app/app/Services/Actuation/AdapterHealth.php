<?php

declare(strict_types=1);

namespace App\Services\Actuation;

/**
 * Whether this platform can still write to a tenant's site right now.
 *
 * ⚠️ **SEPARATE FROM {@see AdapterOutcome} ON PURPOSE.** An outcome is about one
 * attempted write; health is the question asked *before* deciding to attempt
 * anything, and `41` Part 2 lists it as its own verb for that reason. A credential
 * revoked inside WordPress is invisible until something asks.
 */
final readonly class AdapterHealth
{
    public function __construct(
        public bool $writable,
        public string $detail,
    ) {}
}
