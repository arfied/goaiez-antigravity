<?php
$content = file_get_contents('app/tests/Modules/X-204/CancelPendingStepsTest.php');
$search = <<<'CODE'
        $service = new ConsentService;
        $service->suppress($tenant->id, '+15550000000', 'sms', 'opt_out');
        $this->assertTrue(true, 'Suppression with no person should not throw');
CODE;
$replace = <<<'CODE'
        $decoyPerson = Person::create([
            'business_id' => $tenant->id,
            'phone' => '+15550008888',
            'first_name' => 'Decoy',
            'last_name' => 'Person',
        ]);

        $decoyRun = CampaignRun::create([
            'business_id' => $tenant->id,
            'person_id' => $decoyPerson->id,
            'campaign_id' => 'decoy-campaign',
            'current_step' => 1,
            'is_active' => true,
            'is_suppressed' => false,
        ]);

        $beforeCount = CampaignRun::where('business_id', $tenant->id)->count();

        $service = new ConsentService;
        $service->suppress($tenant->id, '+15550000000', 'sms', 'opt_out');

        $afterCount = CampaignRun::where('business_id', $tenant->id)->count();
        $this->assertSame($beforeCount, $afterCount, 'CampaignRun count should be unchanged');

        $decoyRun->refresh();
        $this->assertTrue($decoyRun->is_active, 'Decoy CampaignRun should remain untouched');
CODE;
$content = str_replace($search, $replace, $content);
file_put_contents('app/tests/Modules/X-204/CancelPendingStepsTest.php', $content);
