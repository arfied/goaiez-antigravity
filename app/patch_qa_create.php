<?php

$content = file_get_contents('app/Modules/X-181/Actions/QaTicketCreateAction.php');
$content = str_replace('final class QaTicketCreateAction
{', 'final class QaTicketCreateAction
{
    public const SLA_HOURS = 24;

    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function slaHours(): int
    {
        return $this->registry->int(\'qa.ticket.sla_hours\');
    }', $content);

$content = str_replace('int $slaHours = 24', '?int $slaHours = null', $content);
$content = preg_replace('/\$arrivedAt = now\(\);/', '$slaHours ??= $this->slaHours();
        $arrivedAt = now();', $content);

file_put_contents('app/Modules/X-181/Actions/QaTicketCreateAction.php', $content);
