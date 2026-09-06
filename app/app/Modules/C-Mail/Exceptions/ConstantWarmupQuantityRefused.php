<?php

declare(strict_types=1);

namespace App\Modules\CMail\Exceptions;

use RuntimeException;

/**
 * G15-31 — a warm-up volume with no jitter is the signature of automation.
 */
final class ConstantWarmupQuantityRefused extends RuntimeException
{
    public const REFUSAL_CODE = 'WARMUP_CONSTANT_QUANTITY';

    public static function forDay(string $day): self
    {
        return new self("Warm-up quantity for {$day} is a constant; G15-31 requires a range plus jitter.");
    }
}
