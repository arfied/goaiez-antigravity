<?php

declare(strict_types=1);

namespace App\Enums;

enum MailEventType: string
{
    case Sent = 'sent';
    case Queued = 'queued';
    case Unsubscribed = 'unsubscribed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Replied = 'replied';
    case SpamTrap = 'spam-trap';
}
