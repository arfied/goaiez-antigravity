<?php

declare(strict_types=1);

namespace App\Modules\X201\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeEvidence extends Model
{
    /** Owner-facing names for the evidence a fraud defence needs (ruling 227). */
    public const EVIDENCE_LABELS = [
        'call_log' => 'the call log',
        'transcript' => 'the call transcript',
        'delivery_receipt' => 'the delivery receipt',
        'consent_record' => 'the consent record',
    ];

    protected $table = 'dispute_evidence';

    protected $guarded = [];
}
