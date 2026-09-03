<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function completeJob(array \$tenant, array \$person): void
    {
        throw \$this->todo('complete a real job so job.completed fires');
    }
PHP;
$replace = <<<PHP
    private function completeJob(array \$tenant, array \$person): void
    {
        \$jobId = \Illuminate\Support\Facades\DB::table('work_orders')->insertGetId([
            'business_id' => \$tenant['id'],
            'person_id' => \$person['id'],
            'title' => 'Fix AC',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \$techId = 1;
        app(\App\Modules\X171\Actions\JobStateAction::class)->updateState(
            \$tenant['id'],
            \$jobId,
            \$techId,
            'completed'
        );
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
