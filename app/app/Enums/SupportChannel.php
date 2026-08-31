<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Support\SupportInbox;

/**
 * How a support request reached us — T137 `SL-7`'s three channels.
 *
 * ⚠️ **THE ENUM CARRIES ALL THREE AND ONLY ONE OF THEM HAS A DOOR TODAY.** That
 * is deliberate rather than optimistic: {@see SupportInbox}
 * is the seam every channel arrives through, and a channel missing from this
 * enum would mean the seam could not name what it was handed. What is *not*
 * done is pretend the doors exist — `Email` and `Sms` have no consumer in
 * `app/` yet, and the two seams are named in `SupportInbox`'s own docblock.
 *
 * Not an `OutreachChannel`: that enum answers "what did we send a customer on",
 * and its values are wired to consent lanes, `SendPermit` and the Do Not Call
 * registers. A tenant emailing their own vendor is none of those things, and
 * reusing it would put a support request one type-check away from a path that
 * assumes a consent record exists.
 */
enum SupportChannel: string
{
    /** Raised on the tenant's own Support screen, signed in. */
    case App = 'app';

    /** Arrived as email at the goaiez support mailbox. */
    case Email = 'email';

    /** Arrived as a text on the SL-2 spine. */
    case Sms = 'sms';

    /**
     * Outcome language (`22`): what the person did, not the transport we used.
     */
    public function label(): string
    {
        return match ($this) {
            self::App => 'From their account',
            self::Email => 'By email',
            self::Sms => 'By text',
        };
    }
}
