<?php

$content = file_get_contents('app/Modules/X-120/Actions/CardExpiringScanAction.php');
$content = str_replace('final class CardExpiringScanAction
{', 'final class CardExpiringScanAction
{
    public const EXPIRING_WARNING_DAYS = 30;

    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function expiringWarningDays(): int
    {
        return $this->registry->int(\'billing.card.expiring_warning_days\');
    }', $content);

$content = str_replace('if ($daysRemaining >= 0 && $daysRemaining <= 30 && ! $card->alert_sent) {', 'if ($daysRemaining >= 0 && $daysRemaining <= $this->expiringWarningDays() && ! $card->alert_sent) {', $content);

file_put_contents('app/Modules/X-120/Actions/CardExpiringScanAction.php', $content);
