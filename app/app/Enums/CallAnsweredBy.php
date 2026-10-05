<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who answered a call this platform picked up live (`calls.answered_by`, AI receptionist plan 2026-10-05).
 *
 * Null on the column means this platform did not pick the call up at all — every call recorded from the carrier's webhook.
 * Later waves add the cases they write (a transfer to the owner, the worker's own fallback message), each with the CHECK
 * rebuilt in the same migration.
 */
enum CallAnsweredBy: string
{
    /** The AI receptionist answered — written by `VoiceCalls::answerLive()`. */
    case Agent = 'agent';
}
