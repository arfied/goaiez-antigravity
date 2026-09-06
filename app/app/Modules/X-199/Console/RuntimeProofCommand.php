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
        if (! isset($invoiceData['gateway_charge_id']) || ! str_starts_with($invoiceData['gateway_charge_id'], 'ch_')) {
            $this->error('invoice.json gateway_charge_id must start with ch_');

            return self::FAILURE;
        }

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

        $hasName = str_contains($junitContent, 'test_an_invoice_reaches_a_real_charge_id_and_its_number_cannot_repeat') || str_contains($junitContent, 'An invoice reaches a real charge id and its number cannot repeat');

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
            $this->error('junit.xml does not name test_an_invoice_reaches_a_real_charge_id_and_its_number_cannot_repeat with failures=0 errors=0');

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

        $proof = [
            'artifact_id' => $invoiceData['gateway_charge_id'],
            'driver' => $queueDriver,
            'captured_at' => $capturedAt,
            'junit' => 'storage/app/evidence/X-199/junit.xml',
            'module' => 'X-199',
            'test' => 'test_an_invoice_reaches_a_real_charge_id_and_its_number_cannot_repeat',
        ];

        File::put(storage_path('app/evidence/X-199/runtime-proof.json'), json_encode($proof, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
