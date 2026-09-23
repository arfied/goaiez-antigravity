<?php

$content = file_get_contents('app/Modules/X-172/Actions/PortalLinkAction.php');
$content = str_replace('final class PortalLinkAction
{', 'final class PortalLinkAction
{
    public const TTL_HOURS = 72;

    public function __construct(
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}

    private function ttlHours(): int
    {
        return $this->registry->int(\'portal.link.ttl_hours\');
    }', $content);

$content = str_replace('int $ttlHours = 72', '?int $ttlHours = null', $content);
$content = preg_replace('/PortalLink::where\(\'business_id\', \$businessId\)/', '$ttlHours ??= $this->ttlHours();
        // Deactivate older links for same resource
        PortalLink::where(\'business_id\', $businessId)', $content);

file_put_contents('app/Modules/X-172/Actions/PortalLinkAction.php', $content);
