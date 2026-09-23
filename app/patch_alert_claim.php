<?php

$content = file_get_contents('app/Modules/X-153/Actions/AlertClaimAction.php');
$content = str_replace('final class AlertClaimAction
{', 'final class AlertClaimAction
{
    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function claimExpiryMinutes(): int
    {
        return $this->registry->int(\'alerts.claim.expiry_minutes\');
    }', $content);

$content = str_replace('\'expires_at\' => now()->addMinutes(30),', '\'expires_at\' => now()->addMinutes($this->claimExpiryMinutes()),', $content);

file_put_contents('app/Modules/X-153/Actions/AlertClaimAction.php', $content);
