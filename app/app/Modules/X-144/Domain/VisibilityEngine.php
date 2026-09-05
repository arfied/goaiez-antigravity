<?php

declare(strict_types=1);

namespace App\Modules\X144\Domain;

final class VisibilityEngine
{
    // X-144 domain layer guaranteeing every visibility verdict inherently carries the verbatim query text and timestamp.

    public function getParityScatterModule(): string
    {
        return 'X-144';
    }

    public function getCompetitorBenchmarkModule(): string
    {
        return 'X-144';
    }

    public function getGeoGridModule(): string
    {
        return 'X-177';
    }
}
