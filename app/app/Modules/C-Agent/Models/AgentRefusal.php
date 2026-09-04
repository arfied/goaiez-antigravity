<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property int $session_id
 * @property string $refusal_code
 * @property string $reason
 * @property ?array<string, mixed> $context
 */
class AgentRefusal extends Model
{
    public const VALID_REFUSAL_CODES = [
        'NO_FACT',
        'CONSENT_MISSING',
        'DNC_SUPPRESSED',
        'UNDER_18',
        'PRICE_UNGROUNDED',
        'UNSUPPORTED_ACTION',
        'PROMPT_INJECTION_ATTEMPT',
        'OUT_OF_BUDGET',
        'OUTSIDE_QUIET_HOURS',
        'NEGATIVE_SENTIMENT_HANDOFF',
        'HUMAN_TAKEOVER_LATCH',
        'UNVERIFIED_CALLER',
        'GEO_UNAVAILABLE',
        'RESTRICTED_TOPIC',
        'PAYMENT_UNCONFIRMED',
        'SAMPLE_STATE_REFUSED',
        'SPAM_DETECTED',
        'RATE_LIMIT_EXCEEDED',
        'UNAUTHENTICATED_ACCESS',
        'SCHEMA_VALIDATION_FAILED',
    ];

    protected $table = 'agent_refusals';

    protected $guarded = [];
}
