<?php

$content = file_get_contents('app/Modules/X-181/Actions/QaTicketReopenAction.php');
$content = str_replace('final class QaTicketReopenAction
{', 'final class QaTicketReopenAction
{
    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function slaHours(): int
    {
        return $this->registry->int(\'qa.ticket.sla_hours\');
    }', $content);

$content = str_replace('\'sla_due_at\' => now()->addHours(24),', '\'sla_due_at\' => now()->addHours($this->slaHours()),', $content);

file_put_contents('app/Modules/X-181/Actions/QaTicketReopenAction.php', $content);
