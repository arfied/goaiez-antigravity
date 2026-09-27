<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\PlatformSetting;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class DemoDoorTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_the_demo_door_does_not_say_live_while_no_demo_number_is_set(): void
    {
        $this->get('/demo/trades')
            ->assertOk()
            ->assertSee('See how it answers a trades business')
            ->assertDontSee('— live')
            ->assertDontSee('A live demo you run from your own phone');
    }

    public function test_the_demo_door_says_live_only_with_a_number_and_a_keyword(): void
    {
        PlatformSetting::write('demo.number', '800-555-0199', 'test');
        PlatformSetting::write('demo.keyword.trades', 'TRADES4920', 'test');

        $this->get('/demo/trades')
            ->assertOk()
            ->assertSee('Watch it answer a trades business — live')
            ->assertSee('TRADES4920');
    }
}
