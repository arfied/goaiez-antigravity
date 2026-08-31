<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Messaging\SendingGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One automatic platform halt, and the numbers that caused it.
 *
 * ⛔ **NOT THE SWITCH.** {@see SendingGuard::AUTOMATIC_HALT_KEY}
 * in the defaults registry is what an automatic trip actually throws, and
 * `SendingGuard` reads it to refuse a send; this is the evidence beside it. See
 * the creating migration for why the two are deliberately not the same thing,
 * and why a second row answering "is the platform halted" would be 2186's
 * defect one level up.
 *
 * ⚠️ **THE KEY IT NAMES CHANGED ON 2026-08-15** (3980–3983) and this docblock
 * said `messaging.global_halt` until then. That key is now the operator's
 * alone, because `ComplianceReplies` honours it and a machine must not be able
 * to silence a carrier-mandated STOP or HELP reply.
 *
 * Untenanted and un-RLS'd, on the `compliance_suppressions` precedent (483) —
 * the allowlist entry in `TenancyTest` carries the reasoning.
 *
 * @property int $id
 * @property int $delivered_in_window
 * @property int $complaints_in_window
 * @property int $rate_basis_points
 * @property int $threshold_basis_points
 * @property int $window_hours
 * @property int $tenant_count
 * @property CarbonImmutable $tripped_at
 */
final class PlatformHaltIncident extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'delivered_in_window',
        'complaints_in_window',
        'rate_basis_points',
        'threshold_basis_points',
        'window_hours',
        'tenant_count',
        'tripped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivered_in_window' => 'integer',
            'complaints_in_window' => 'integer',
            'rate_basis_points' => 'integer',
            'threshold_basis_points' => 'integer',
            'window_hours' => 'integer',
            'tenant_count' => 'integer',
            'tripped_at' => 'immutable_datetime',
        ];
    }
}
