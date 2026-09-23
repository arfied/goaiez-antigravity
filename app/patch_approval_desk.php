<?php

$content = file_get_contents('app/Modules/X-202/Domain/ApprovalDeskEngine.php');
$content = str_replace('final class ApprovalDeskEngine
{', 'final class ApprovalDeskEngine
{
    public const EXPIRY_HOURS = 72;

    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function expiryHours(): int
    {
        return $this->registry->int(\'approvals.expiry_hours\');
    }', $content);

$content = str_replace('int $expiresInHours = 72', '?int $expiresInHours = null', $content);
$content = preg_replace('/if \(empty\(\$itemType\)/', '$expiresInHours ??= $this->expiryHours();
        if (empty($itemType)', $content);

file_put_contents('app/Modules/X-202/Domain/ApprovalDeskEngine.php', $content);
