<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OperatorAlertKind;
use App\Services\Ops\OperatorAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One bell, rung once — P23.
 *
 * ⚠️ **THE ROW IS THE DE-DUPLICATION AS WELL AS THE RECORD.**
 * {@see OperatorAlerts::raise()} refuses to fire again while a row of the same
 * kind and subject is inside the quiet window, so deleting rows here does not
 * clean anything up: it re-arms every alert that was already sent.
 *
 * ⛔ **NOTHING PERSONAL IS EVER WRITTEN HERE.** `summary` becomes the body of a
 * text message and `context` is read on an Ops screen; both carry counts, rates,
 * thresholds and names of ours, and never a customer, a phone number or a
 * vendor's raw error string. The creating migration says why the last of those
 * matters as much as the first two.
 *
 * ⚠️ **`push_withheld_at` IS A THIRD OUTCOME AND NOT A FOURTH TIMESTAMP.** Two
 * nulls on `emailed_at` and `texted_at` used to mean one thing — nobody was
 * listening, or both channels failed. They now also mean *"the push was withheld
 * on purpose"*, which is the opposite diagnosis, and this column is what tells
 * them apart. {@see OperatorAlerts::raise()} carries the argument.
 *
 * ✅ **AND IT HAS A READER ON A SCREEN SINCE 2026-08-22, WHICH IS THE HALF IT
 * WAS MISSING** (7960–7979). It landed with one reader — a `composer deploy`
 * step — and **no blade rendered it anywhere**, so the state it exists to name
 * was still being described to a person as *"no record of this being emailed or
 * texted"*: the exact conflation it was minted to end, surviving in the one
 * place somebody actually reads. `Admin\OperatorAlertBoard` now says it per row
 * in the log, and totals it per kind.
 *
 * ⛔ **THAT SENTENCE ENDED "WHEN A FLOOD BOUNDS THE INCIDENT VIEW" AND THAT WAS
 * THE DEFECT RATHER THAN THE FEATURE — CORRECTED 2026-08-22** (8120–8139;
 * 4368's rule, both readings kept). It was exactly true: the totals lived in
 * `spread()`, which renders only when more `(kind, subject)` pairs rang than the
 * page draws. **That is a breadth condition and a spent push budget is a depth
 * one** — the budget is per *kind*, so one account on one kind rings twenty-four
 * times a day at the seeded quiet window and spends that kind's whole allowance
 * by itself, on a screen drawing a single line with no summary at all. The
 * totals are `withheld()`'s now and are behind no gate.
 *
 * Untenanted and un-RLS'd, on `platform_halt_incidents`' precedent (2119) — the
 * allowlist entry in `TenancyTest` carries the reasoning.
 *
 * @property int $id
 * @property OperatorAlertKind $kind
 * @property string $subject
 * @property string $summary
 * @property array<string, scalar|null> $context
 * @property CarbonImmutable $fired_at
 * @property CarbonImmutable|null $emailed_at
 * @property CarbonImmutable|null $texted_at
 * @property CarbonImmutable|null $push_withheld_at
 */
final class OperatorAlert extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'kind',
        'subject',
        'summary',
        'context',
        'fired_at',
        'emailed_at',
        'texted_at',
        'push_withheld_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OperatorAlertKind::class,
            'context' => 'array',
            'fired_at' => 'immutable_datetime',
            'emailed_at' => 'immutable_datetime',
            'texted_at' => 'immutable_datetime',
            'push_withheld_at' => 'immutable_datetime',
        ];
    }
}
