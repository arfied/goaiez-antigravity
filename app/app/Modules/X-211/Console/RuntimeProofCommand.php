<?php

declare(strict_types=1);

namespace App\Modules\X211\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class RuntimeProofCommand extends Command
{
    protected $signature = 'x211:runtime-proof';

    protected $description = 'Generate runtime proof for X-211';

    public function handle(): int
    {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $recoveryPath = storage_path('app/evidence/X-211/recovery.json');
        if (! File::exists($recoveryPath)) {
            $this->error('Missing evidence/X-211/recovery.json');

            return self::FAILURE;
        }

        $recoveryData = json_decode(File::get($recoveryPath), true);

        $queueDriver = $recoveryData['queue_driver'] ?? '';
        if ($queueDriver === '' || $queueDriver === 'sync') {
            $this->error('recovery.json driver cannot be missing, empty, or sync');

            return self::FAILURE;
        }

        $junitPath = storage_path('app/evidence/X-211/junit.xml');
        if (! File::exists($junitPath)) {
            $this->error('Missing evidence/X-211/junit.xml');

            return self::FAILURE;
        }

        $junitContent = File::get($junitPath);
        $junitXml = simplexml_load_string($junitContent);
        if ($junitXml === false) {
            $this->error('Invalid junit.xml');

            return self::FAILURE;
        }

        $hasName = str_contains($junitContent, 'test_the_recovery_artifact_proves_the_plan_and_its_refusal') || str_contains($junitContent, 'The recovery artifact proves the plan and its refusal');

        $rootSuite = null;
        if ($junitXml->getName() === 'testsuites' && isset($junitXml->testsuite[0])) {
            $rootSuite = $junitXml->testsuite[0];
        } elseif ($junitXml->getName() === 'testsuite') {
            $rootSuite = $junitXml;
        }

        if (! $rootSuite) {
            $this->error('junit.xml does not contain a root testsuite');

            return self::FAILURE;
        }

        $failures = (string) $rootSuite['failures'];
        $errors = (string) $rootSuite['errors'];

        if (! $hasName || $failures !== '0' || $errors !== '0') {
            $this->error('junit.xml does not name test_the_recovery_artifact_proves_the_plan_and_its_refusal with failures=0 errors=0');

            return self::FAILURE;
        }

        $capturedAt = null;
        if (isset($rootSuite['timestamp'])) {
            $capturedAt = (string) $rootSuite['timestamp'];
        } elseif (! empty($recoveryData['captured_at'])) {
            $capturedAt = $recoveryData['captured_at'];
        } else {
            $this->error('Neither junit.xml nor recovery.json provided a capture time');

            return self::FAILURE;
        }

        $this->error('X-211 has no runtime proof: a recovery cannot reach a gateway charge id, because payments carries no plan column (see ruling 102). No artifact was written.');

        return self::FAILURE;
    }
}
