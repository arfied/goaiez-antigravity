<?php

declare(strict_types=1);

namespace App\Services\Consent;

/**
 * The owner channel's own disclosure wording (10540), `AutoRenewalDisclosure`'s
 * shape: the version and the words live together so the two cannot drift.
 *
 * ⚠️ Bump the version when the words change; never reuse a version for
 * different text. `OwnerConsentService::capture()` stores this version beside
 * the words it names, so grouping every owner who agreed to one statement is
 * the one thing the record is for.
 */
final class OwnerNotifyDisclosure
{
    public const string VERSION = 'owner-notify-2026-08-27.1';

    public const string TEXT = 'We will text this number about your own account — things '
        .'like an urgent message from a customer, or something that needs your reply. '
        .'Message and data rates may apply. Reply STOP to stop, HELP for help.';
}
