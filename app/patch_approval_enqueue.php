<?php

$content = file_get_contents('app/Modules/X-202/Actions/ApprovalEnqueueAction.php');
$content = str_replace('public function __construct(private readonly ApprovalDeskEngine $engine) {}', 'public function __construct(
        private readonly ApprovalDeskEngine $engine,
        private readonly \App\Support\DefaultsRegistry $registry
    ) {}', $content);

$content = str_replace('int $expiresInHours = 72', '?int $expiresInHours = null', $content);
$content = preg_replace('/return \$this->engine->enqueue/', '$expiresInHours ??= $this->registry->int(\'approvals.expiry_hours\');
        return $this->engine->enqueue', $content);

file_put_contents('app/Modules/X-202/Actions/ApprovalEnqueueAction.php', $content);
