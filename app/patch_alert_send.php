<?php

$content = file_get_contents('app/Modules/X-153/Actions/AlertSendAction.php');
$content = str_replace('final class AlertSendAction
{', 'final class AlertSendAction
{
    public const CLAIM_EXPIRY_MINUTES = 30;

    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function claimExpiryMinutes(): int
    {
        return $this->registry->int(\'alerts.claim.expiry_minutes\');
    }', $content);

$content = str_replace('\'claim_expires_at\' => now()->addMinutes(30),', '\'claim_expires_at\' => now()->addMinutes($this->claimExpiryMinutes()),', $content);

file_put_contents('app/Modules/X-153/Actions/AlertSendAction.php', $content);
