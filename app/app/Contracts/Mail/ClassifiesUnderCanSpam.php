<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

use App\Enums\CanSpamClass;

/**
 * Every notification this application can email says which CAN-SPAM class it is
 * — T176 P21.
 *
 * ⚠️ **A METHOD ON THE NOTIFICATION RATHER THAN A REGISTRY, AND THAT IS THE
 * WHOLE DESIGN.** A `match` over notification class names in one central file
 * would be a second source of truth that drifts the first time somebody renames
 * a class, and — worse — it would answer for a class it has never heard of.
 * Here the answer travels with the message, the argument sits in the same
 * docblock as the copy it is an argument about, and a notification that has not
 * answered **cannot be delivered**: `PlatformMailer::deliverNow()` refuses one.
 *
 * ⚠️ **THE REFUSAL IS THE MECHANISM AND THE LINT IS THE EARLY WARNING.**
 * `tests/Feature/Architecture/MailTest.php` fails the build when a class under
 * `app/Notifications` does not implement this, which turns "somebody's first
 * email went out with no unsubscribe link" into a red suite on the commit that
 * added the class. It can fail today — it found nothing only because this slice
 * added the declaration to all twelve existing notifications in the same
 * change — and 256's rule is that the lint you ship is the one that can fail
 * now, not the one that will match later.
 */
interface ClassifiesUnderCanSpam
{
    /**
     * Commercial, or transactional/relationship — with the argument in the
     * implementing class's docblock, never only here.
     */
    public function canSpamClass(): CanSpamClass;
}
