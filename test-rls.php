<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');
$target = <<<PHP
        \$this->drainQueue();
        \Illuminate\Support\Facades\Log::warning("failed_jobs: " . json_encode(\Illuminate\Support\Facades\DB::table('failed_jobs')->get()));
        \Illuminate\Support\Facades\Log::warning("calls rows: " . json_encode(\Illuminate\Support\Facades\DB::table('calls')->get()));
PHP;
$replacement = <<<PHP
        \$this->drainQueue();
        \App\Support\Tenancy::set(\$biz->id); // RESTORE TENANCY!
        \Illuminate\Support\Facades\Log::warning("failed_jobs: " . json_encode(\Illuminate\Support\Facades\DB::table('failed_jobs')->get()));
        \Illuminate\Support\Facades\Log::warning("calls rows: " . json_encode(\Illuminate\Support\Facades\DB::table('calls')->get()));
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
