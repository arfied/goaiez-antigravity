<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which side of the desk one message came from — T137 `SL-7`.
 *
 * ⚠️ **A SIDE, NOT A PERSON.** `author_user_id` names who typed it and is
 * nullable; this is not, because a message whose side is unknown is a message
 * the thread cannot render honestly. An inbound email from an address matching
 * no account user is still unambiguously the tenant speaking.
 */
enum SupportMessageAuthor: string
{
    /** The tenant — the owner or somebody on their account. */
    case Owner = 'owner';

    /** Us. */
    case Staff = 'staff';

    /**
     * How the thread reads on the tenant's own screen.
     *
     * ⚠️ **TWO LABELS RATHER THAN ONE PLUS A TERNARY IN A TEMPLATE.** The same
     * row is rendered on two screens for two audiences, and "You" is wrong on
     * one of them — a template choosing its own wording is one that drifts from
     * the next template (2652). Both live here, both are named for their
     * audience, and neither has a default so a third side is a conversation.
     */
    public function labelForTenant(): string
    {
        return match ($this) {
            self::Owner => 'You',
            self::Staff => 'GO AI EZ support',
        };
    }

    /** How the thread reads in the console. */
    public function labelForStaff(): string
    {
        return match ($this) {
            self::Owner => 'The account',
            self::Staff => 'Us',
        };
    }
}
