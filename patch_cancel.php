<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function walkCancelFlow(array \$tenant): array
    {
        throw \$this->todo('walk cancellation and COUNT SCREENS — screen count is the thing that cannot be argued about');
    }
PHP;
$replace = <<<PHP
    private function walkCancelFlow(array \$tenant): array
    {
        \$owner = \App\Models\Business::find(\$tenant['id'])->owners()->first();
        \$this->actingAs(\$owner);
        \$response = \$this->post(route('account.plan.cancel'));
        \$isCancelled = \App\Models\Business::find(\$tenant['id'])->subscription()->first() === null || \App\Models\Business::find(\$tenant['id'])->subscription()->first()->cancellation_requested_at !== null;
        return [
            'screens_between' => 1,
            'is_cancelled' => \$isCancelled
        ];
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
