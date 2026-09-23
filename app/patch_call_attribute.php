<?php

$content = file_get_contents('app/Modules/X-137/Actions/CallAttributeAction.php');
$content = str_replace('final class CallAttributeAction
{', 'final class CallAttributeAction
{
    public const TTL_MINUTES = 30;

    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function ttlMinutes(): int
    {
        return $this->registry->int(\'attribution.call.ttl_minutes\');
    }', $content);

$content = str_replace('int $ttlMinutes = 30', '?int $ttlMinutes = null', $content);
$content = preg_replace('/if \(trim\(\(string\) \$visitorSessionToken\)/', '$ttlMinutes ??= $this->ttlMinutes();
        if (trim((string) $visitorSessionToken)', $content, 1);
$content = preg_replace('/if \(\$offlineCampaign\)/', '$ttlMinutes ??= $this->ttlMinutes();
        if ($offlineCampaign)', $content, 1);

file_put_contents('app/Modules/X-137/Actions/CallAttributeAction.php', $content);
