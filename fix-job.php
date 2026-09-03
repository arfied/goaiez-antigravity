<?php
$content = file_get_contents('app/app/Jobs/SendMissedCallTextBackJob.php');
$target = "    public function automationKey(): string\n    {\n        return 'voice.missed_call.text_back';\n    }";
$replacement = <<<PHP
    public function automationKey(): string
    {
        return \App\Enums\AutopilotActionType::CallMissed->value;
    }
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/app/Jobs/SendMissedCallTextBackJob.php', $content);
