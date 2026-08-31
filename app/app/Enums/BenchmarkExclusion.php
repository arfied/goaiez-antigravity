<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why one business contributed nothing to a day's network benchmarks.
 *
 * ⚠️ **AN ENUM RATHER THAN THE THREE MAGIC STRINGS THIS STARTED AS.** The first
 * draft of [[\App\Services\Warehouse\NetworkBenchmarks]] returned
 * `'covered'|'unrecognised'|'silent'` and counted them in a `match` with a
 * `default` arm — so a fourth reason, or a typo, would have been counted as
 * "silent" and reported to an operator as a tenant with nothing to say. The
 * `match` over these cases is exhaustive and has no `default`.
 *
 * ⛔ **THE COUNTS ARE THE POINT, NOT THE TIDINESS.** On any real deployment
 * today every business is `UnrecognisedVertical`, because nothing writes
 * `businesses.vertical` (decision 5940) — so "nothing was published" and
 * "nothing was there to publish" are the same green unless the reason is
 * carried out of the walk and printed. That is the failure `CLAUDE.md` records
 * over and over: a layer that is silently empty reads as a layer that works.
 */
enum BenchmarkExclusion: string
{
    /**
     * The stored vertical is not a [[BenchmarkVertical]] — which today is every
     * business on any real deployment, and is fail-closed either way: an
     * unrecognised value excludes a tenant rather than opening a cohort named
     * after whatever was typed into the column.
     */
    case UnrecognisedVertical = 'unrecognised_vertical';

    /**
     * A recognised vertical and no L2 row for the day, or a row from which no
     * metric could be computed at all.
     */
    case NoMeasurements = 'no_measurements';
}
