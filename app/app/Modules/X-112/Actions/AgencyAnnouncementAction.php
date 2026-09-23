<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

final class AgencyAnnouncementAction
{
    /**
     * [G16-10] agency announcements
     */
    public function registerMandatoryAnnouncement(string $message): array
    {
        // R245: An un-dismissible popup is not a notification class we have (P-062).
        // Therefore we implement it as a blocking modal requirement in the domain.
        return [
            'type' => 'mandatory_announcement',
            'message' => $message,
            'requires_acknowledgement' => true,
        ];
    }
}
