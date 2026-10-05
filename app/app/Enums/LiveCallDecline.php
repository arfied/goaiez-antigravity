<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why call start told the voice worker not to answer with the AI receptionist (AI receptionist plan, 2026-10-05).
 *
 * A declined call is not dropped: the worker plays its stored fallback — the recording announcement, then "leave your name
 * and number" — and the message reaches the owner. Each reason is one the worker cannot fix and an operator can read.
 */
enum LiveCallDecline: string
{
    /** Nobody has attested the recording announcement is configured, so nothing may be answered and recorded. */
    case AnnouncementNotAttested = 'announcement_not_attested';

    /** The dialled number belongs to no business (the shared pool number, a released number). */
    case UnknownNumber = 'unknown_number';

    /** The business chose to be rung first, and ringing the owner from a live call is not built yet (wave 5). */
    case OwnerFirstNotBuilt = 'owner_first_not_built';

    /** The business's AI spend ceiling refuses another call (`AiSpend::refusal()`). */
    case AiSpendRefused = 'ai_spend_refused';

    /** The business's inbound minutes today crossed `voice.tenant_daily_inbound_minutes_ceiling`. */
    case DailyMinutesCeiling = 'daily_minutes_ceiling';
}
