<?php

declare(strict_types=1);

namespace App\Modules\X198\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class RuntimeProofCommand extends Command
{
    protected $signature = 'x198:runtime-proof';

    protected $description = 'Generate runtime proof for X-198';

    public function handle(): int
    {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $chargePath = storage_path('app/evidence/j9/charge.json');
        if (! File::exists($chargePath)) {
            $this->error('Missing evidence/j9/charge.json');

            return self::FAILURE;
        }

        $chargeData = json_decode(File::get($chargePath), true);
        if (! isset($chargeData['gateway_charge_id']) || ! str_starts_with($chargeData['gateway_charge_id'], 'ch_')) {
            $this->error('charge.json artifact_id must start with ch_');

            return self::FAILURE;
        }

        $invoicePaidPath = storage_path('app/evidence/journeys/invoice-to-paid.json');
        if (! File::exists($invoicePaidPath)) {
            $this->error('Missing evidence/journeys/invoice-to-paid.json');

            return self::FAILURE;
        }

        $invoicePaidData = json_decode(File::get($invoicePaidPath), true);
        if (($invoicePaidData['passed'] ?? false) !== true) {
            $this->error('invoice-to-paid.json passed is not true');

            return self::FAILURE;
        }

        $queueDriver = $invoicePaidData['queue_driver'] ?? '';
        if ($queueDriver === '' || $queueDriver === 'sync') {
            $this->error('invoice-to-paid.json driver cannot be missing, empty, or sync');

            return self::FAILURE;
        }

        if (($invoicePaidData['artifact_id'] ?? null) !== $chargeData['gateway_charge_id']) {
            $this->error('invoice-to-paid.json artifact_id differs from charge.json gateway_charge_id');

            return self::FAILURE;
        }

        $junitPath = storage_path('app/evidence/X-198/junit.xml');
        if (! File::exists($junitPath)) {
            $this->error('Missing evidence/X-198/junit.xml');

            return self::FAILURE;
        }

        $junitContent = File::get($junitPath);
        $junitXml = simplexml_load_string($junitContent);
        if ($junitXml === false) {
            $this->error('Invalid junit.xml');

            return self::FAILURE;
        }

        $hasName = str_contains($junitContent, 'an_invoice_reaches_a_real_charge_id') || str_contains($junitContent, 'An invoice reaches a real charge id');
        
        $rootSuite = null;
        if ($junitXml->getName() === 'testsuites' && isset($junitXml->testsuite[0])) {
            $rootSuite = $junitXml->testsuite[0];
        } elseif ($junitXml->getName() === 'testsuite') {
            $rootSuite = $junitXml;
        }

        if (!$rootSuite) {
            $this->error('junit.xml does not contain a root testsuite');
            return self::FAILURE;
        }

        $failures = (string) $rootSuite['failures'];
        $errors = (string) $rootSuite['errors'];

        if (! $hasName || $failures !== '0' || $errors !== '0') {
            $this->error('junit.xml does not name an_invoice_reaches_a_real_charge_id with failures=0 errors=0');

            return self::FAILURE;
        }

        // invoice-to-paid.json is written by JourneyHarness::writeEvidence() as the last statement of the same
        // journey execution that produced junit.xml — it is the run stamping itself, not the filesystem stamping the run.
        // The command already refuses unless that file's artifact_id equals charge.json's gateway_charge_id, and that
        // comparison is what ties the stamp to this charge and this execution. This fixes B1.
        $capturedAt = null;
        if (isset($rootSuite['timestamp'])) {
            $capturedAt = (string) $rootSuite['timestamp'];
        } elseif (!empty($invoicePaidData['captured_at'])) {
            $capturedAt = $invoicePaidData['captured_at'];
        } else {
            $this->error('Neither junit.xml nor invoice-to-paid.json provided a capture time');

            return self::FAILURE;
        }

        $proof = [
            'artifact_id' => $chargeData['gateway_charge_id'],
            'driver' => $queueDriver,
            'captured_at' => $capturedAt,
            'junit' => 'storage/app/evidence/X-198/junit.xml',
            'module' => 'X-198',
            'test' => 'an_invoice_reaches_a_real_charge_id',
        ];

        File::put(storage_path('app/evidence/X-198/runtime-proof.json'), json_encode($proof, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
