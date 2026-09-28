<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Enums\VitalSampleState;
use App\Modules\X103\Domain\PageVariantReading;
use App\Modules\X108\Actions\BookingReadAction;
use App\Modules\X157\Actions\DeploymentArmReadAction;
use App\Services\Warehouse\SiteVitals;

class PageVariantResultAction
{
    public function __construct(
        private DeploymentArmReadAction $deploymentArmReadAction,
        private BookingReadAction $bookingReadAction
    ) {}

    /**
     * @return array{control: PageVariantReading, variant: PageVariantReading, leader: ?string}
     */
    public function handle(int $businessId, int $variantId): array
    {
        $arms = $this->deploymentArmReadAction->forVariant($businessId, $variantId);

        $readArm = function (string $armName, ?array $armData) use ($businessId): PageVariantReading {
            if (! $armData) {
                return new PageVariantReading($armName, VitalSampleState::NoMeasurements, 0, 0, 0);
            }

            $served = $armData['served'];
            $requests = $this->bookingReadAction->requestsForDeployment($businessId, $armData['hash']);

            if ($served >= SiteVitals::MINIMUM_SAMPLES) {
                $rate = intdiv($requests * 10_000, $served);

                return new PageVariantReading($armName, VitalSampleState::Measured, $served, $requests, $rate);
            }

            $state = $served === 0 ? VitalSampleState::NoMeasurements : VitalSampleState::InsufficientData;

            return new PageVariantReading($armName, $state, $served, $requests, 0);
        };

        $control = $readArm('control', $arms['control']);
        $variant = $readArm('variant', $arms['variant']);

        $leader = null;
        if ($control->isMeasured() && $variant->isMeasured()) {
            if ($variant->ratePerTenThousand > $control->ratePerTenThousand) {
                $leader = 'variant';
            } elseif ($control->ratePerTenThousand > $variant->ratePerTenThousand) {
                $leader = 'control';
            }
        }

        return [
            'control' => $control,
            'variant' => $variant,
            'leader' => $leader,
        ];
    }
}
