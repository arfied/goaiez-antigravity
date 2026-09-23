<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

final class PasswordlessReportAction
{
    /**
     * [G4-22] the agency's client sees results without a password
     */
    public function generateSignedUrl(string $clientId): string
    {
        // R245: We generate a cryptographically signed URL to bypass the auth middleware securely.
        $signature = hash_hmac('sha256', $clientId, 'secret-app-key');

        return "/client/report/{$clientId}?signature={$signature}";
    }
}
