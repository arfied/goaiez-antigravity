<?php

declare(strict_types=1);

namespace Tests\Modules\X198;

require_once __DIR__ . "/../../../app/Modules/X-198/Domain/ProcessorAdapter.php";
require_once __DIR__ . "/../../../app/Modules/X-198/Actions/MerchantApplyAction.php";
require_once __DIR__ . "/../../../app/Modules/X-198/Events/MerchantApplied.php";

use App\Models\User;
use App\Modules\X198\Domain\ProcessorAdapter;
use App\Modules\X198\Ui\ConnectCard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectCardScreenTest extends TestCase
{
    public function test_connect_card_refuses_when_no_adapter_bound(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->call('applyForMerchant')
            ->assertSee('No processor adapter bound.');
    }

    public function test_connect_card_applies_when_adapter_bound(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $adapter = new class implements ProcessorAdapter {
            public function applyForSubMerchant(int $businessId): string {
                return 'stripe';
            }
        };

        $this->app->instance(ProcessorAdapter::class, $adapter);

        Livewire::actingAs($owner)->test(ConnectCard::class)
            ->call('applyForMerchant')
            ->assertSee('Application in progress');
    }
}
