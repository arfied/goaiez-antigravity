<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who picks up first when a call reaches a business's number — owner ruling D-6, 2026-10-05 (AI receptionist plan).
 *
 * Stored on `support_settings.live_answer_mode`, written and read by `CallForwarding` only.
 */
enum LiveAnswerMode: string
{
    /** The AI receptionist answers every call straight away. The owner's chosen default. */
    case AiFirst = 'ai_first';

    /**
     * The owner's phone rings first and the AI receptionist answers if nobody picks up.
     *
     * ⚠️ Ringing the owner from a live call is wave 5 of the plan. Until it exists, call start declines a call for a business
     * on this mode (`LiveCallDecline::OwnerFirstNotBuilt`) and the voice worker takes a message, rather than answering with
     * the AI the owner asked to come second.
     */
    case OwnerFirst = 'owner_first';

    public function label(): string
    {
        return match ($this) {
            self::AiFirst => 'AI answers straight away',
            self::OwnerFirst => 'Ring me first',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AiFirst => 'Your AI receptionist picks up every call to your number straight away, and tells the caller it is an AI assistant.',
            self::OwnerFirst => 'Your phone rings first. If you do not pick up, your AI receptionist answers.',
        };
    }
}
