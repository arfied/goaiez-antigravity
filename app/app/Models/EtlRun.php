<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One replay, and the only place in the warehouse a wall clock is written.
 *
 * The migration's docblock carries the argument (decision 4862): a derived row
 * that stamps the moment it was derived cannot be rebuilt byte-identically, so
 * the fact moves off the derived rows and onto the run.
 *
 * ⚠️ **`snapshot_digest` IS WHAT MAKES THIS TABLE USEFUL RATHER THAN MERELY
 * TIDY.** The test suite proves byte-identity by rebuilding twice in one run;
 * an operator with seven years of archive cannot. Comparing this run's digest
 * with the last one over the same range is how a derivation that quietly started
 * producing different bytes gets noticed in production.
 *
 * ⛔ **AND THAT MAKES A SCHEMA CHANGE TO ANY DERIVED TABLE A DELIBERATE BREAK IN
 * THIS COLUMN, WHICH BELONGS WHERE THE COMPARISON IS DESCRIBED RATHER THAN ONLY
 * IN A DECISION ROW.** [[\App\Services\Warehouse\WarehouseSnapshot]] renders a
 * written column list per table, so **a column added or removed moves every
 * digest for every tenant** — the instrument reports exactly what it was built to
 * report, and the cause is the migration rather than the derivation.
 * ⚠️ **It happened on 2026-08-22** (8040, 8041; 8100–8119): `l2_fact_session`
 * lost `day` and gained `first_received_at`, so **no digest recorded before that
 * date is comparable with one recorded after it**, and the first honest
 * comparison over any range is between two runs that both postdate it. A row here
 * is a fact about the schema it was taken under, and nothing in this table
 * records which schema that was.
 *
 * ⚠️ **`l1_rows` AND `l1_suppressed` ARE TWO FACTS AND NEITHER IS THE OTHER'S
 * ERROR BAR** (7924–7929). `l1_rows` is what this range wrote; `l1_suppressed`
 * is what it derived and did not write, because the same `(business_id,
 * event_id)` already lives **outside** the range — a beacon retried across UTC
 * midnight. A non-zero value is not a fault: it is the count of events this
 * range shares with a neighbouring one, and the day of the **first** receipt
 * owns them.
 *
 * @property int $id
 * @property int $business_id
 * @property Carbon $from_day
 * @property Carbon $to_day
 * @property int $l0_objects
 * @property int $l0_lines
 * @property int $l1_rows
 * @property int $l1_suppressed
 * @property int $l2_rows
 * @property int $l0_rejected
 * @property string $snapshot_digest
 * @property Carbon $started_at
 * @property Carbon $finished_at
 */
final class EtlRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'etl_runs';

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_day' => 'immutable_date',
            'to_day' => 'immutable_date',
            'l0_objects' => 'integer',
            'l0_lines' => 'integer',
            'l1_rows' => 'integer',
            'l1_suppressed' => 'integer',
            'l2_rows' => 'integer',
            'l0_rejected' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
