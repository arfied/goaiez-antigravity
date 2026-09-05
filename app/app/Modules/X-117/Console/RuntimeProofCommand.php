<?php

declare(strict_types=1);

namespace App\Modules\X117\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class RuntimeProofCommand extends Command
{
    protected $signature = 'x117:runtime-proof';

    protected $description = 'Generate runtime proof for X-117';

    public function handle(): int
    {
        if (app()->runningUnitTests()) {
            $this->error('The artifact may only be produced by a real CLI run.');

            return self::FAILURE;
        }

        $checkoutPath = storage_path('app/evidence/X-117/checkout.json');
        if (! File::exists($checkoutPath)) {
            $this->error('Missing evidence/X-117/checkout.json');

            return self::FAILURE;
        }

        $checkoutData = json_decode(File::get($checkoutPath), true);
        if (! isset($checkoutData['gateway_charge_id']) || ! str_starts_with($checkoutData['gateway_charge_id'], 'ch_')) {
            $this->error('checkout.json artifact_id must start with ch_');

            return self::FAILURE;
        }

        $queueDriver = $checkoutData['queue_driver'] ?? '';
        if ($queueDriver === '' || $queueDriver === 'sync') {
            $this->error('checkout.json driver cannot be missing, empty, or sync');

            return self::FAILURE;
        }

        $junitPath = storage_path('app/evidence/X-117/junit.xml');
        if (! File::exists($junitPath)) {
            $this->error('Missing evidence/X-117/junit.xml');

            return self::FAILURE;
        }

        $junitContent = File::get($junitPath);
        $junitXml = simplexml_load_string($junitContent);
        if ($junitXml === false) {
            $this->error('Invalid junit.xml');

            return self::FAILURE;
        }

        $hasName = str_contains($junitContent, 'test_checkout_reaches_a_real_charge_id') || str_contains($junitContent, 'Checkout reaches a real charge id');

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
            $this->error('junit.xml does not name test_checkout_reaches_a_real_charge_id with failures=0 errors=0');

            return self::FAILURE;
        }

        $capturedAt = null;
        if (isset($rootSuite['timestamp'])) {
            $capturedAt = (string) $rootSuite['timestamp'];
        } elseif (! empty($checkoutData['captured_at'])) {
            $capturedAt = $checkoutData['captured_at'];
        } else {
            $this->error('Neither junit.xml nor checkout.json provided a capture time');

            return self::FAILURE;
        }

        $proof = [
            'artifact_id' => $checkoutData['gateway_charge_id'],
            'driver' => $queueDriver,
            'captured_at' => $capturedAt,
            'junit' => 'storage/app/evidence/X-117/junit.xml',
            'module' => 'X-117',
            'test' => 'test_checkout_reaches_a_real_charge_id',
        ];

        File::put(storage_path('app/evidence/X-117/runtime-proof.json'), json_encode($proof, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
