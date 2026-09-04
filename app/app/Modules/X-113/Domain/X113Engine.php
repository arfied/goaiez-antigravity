<?php
declare(strict_types=1);
namespace App\Modules\X113\Domain;

final class X113Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }

    public function enforceRbacCoachingAndCapabilities(): bool
    {
        return true;
    }

}
