<?php

declare(strict_types=1);

namespace App\Modules\X199\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class RuntimeProofCommand extends Command
{
    protected $signature = 'x199:runtime-proof';

    protected $description = 'Generate runtime proof for X-199';

    public function handle(): int
    {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $invoicePath = storage_path('app/evidence/X-199/invoice.json');
        if (! File::exists($invoicePath)) {
            $this->error('Missing evidence/X-199/invoice.json');

            return self::FAILURE;
        }

        $invoiceData = json_decode(File::get($invoicePath), true);

        $queueDriver = $invoiceData['queue_driver'] ?? '';
        if ($queueDriver === '' || $queueDriver === 'sync') {
            $this->error('invoice.json driver cannot be missing, empty, or sync');

            return self::FAILURE;
        }

        $junitPath = storage_path('app/evidence/X-199/junit.xml');
        if (! File::exists($junitPath)) {
            $this->error('Missing evidence/X-199/junit.xml');

            return self::FAILURE;
        }

        $junitContent = File::get($junitPath);
        $junitXml = simplexml_load_string($junitContent);
        if ($junitXml === false) {
            $this->error('Invalid junit.xml');

            return self::FAILURE;
        }

        $hasName = str_contains($junitContent, 'test_the_invoice_artifact_proves_its_number_sequence_and_refuses_a_duplicate') || str_contains($junitContent, 'The invoice artifact proves its number sequence and refuses a duplicate');

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
            $this->error('junit.xml does not name test_the_invoice_artifact_proves_its_number_sequence_and_refuses_a_duplicate with failures=0 errors=0');

            return self::FAILURE;
        }

        $capturedAt = null;
        if (isset($rootSuite['timestamp'])) {
            $capturedAt = (string) $rootSuite['timestamp'];
        } elseif (! empty($invoiceData['captured_at'])) {
            $capturedAt = $invoiceData['captured_at'];
        } else {
            $this->error('Neither junit.xml nor invoice.json provided a capture time');

            return self::FAILURE;
        }

        $this->error('X-199 has no runtime proof: an invoice cannot reach a gateway charge id, because payments carries no invoice column (see ruling 102). No artifact was written.');

        return self::FAILURE;
    }
}
