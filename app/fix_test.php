<?php

$content = file_get_contents('tests/Modules/X-171/X171Test.php');

$new = <<<'PHP'
    public function test_defect_arm_cannot_read_cross_tenant_person_id(): void
    {
        Event::fake([JobCompleted::class]);
        $bizA = TestCase::provisionTenant(['name' => 'Tenant A', 'currency' => 'USD']);
        $bizB = TestCase::provisionTenant(['name' => 'Tenant B', 'currency' => 'USD']);

        $personId = DB::table('people')->insertGetId(['business_id' => $bizB->id]);

        $jobIdOwnedByB = DB::table('work_orders')->insertGetId([
            'business_id' => $bizB->id,
            'title' => 'Cross-tenant tap',
            'scheduled_at' => '2025-01-01 10:00:00',
            'created_at' => '2025-01-01 09:00:00',
            'updated_at' => '2025-01-01 09:00:00',
            'person_id' => $personId,
        ]);

        $action = new JobStateAction;
        $action->updateState($bizA->id, $jobIdOwnedByB, 3, 'completed');

        Event::assertDispatched(JobCompleted::class, function ($e) {
            $this->assertNull($e->personId, 'Tenant A must not read Tenant B person_id');
            return true;
        });
    }

    public function test_regression_arm_reads_own_person_id(): void
    {
        Event::fake([JobCompleted::class]);
        $bizA = TestCase::provisionTenant(['name' => 'Tenant A', 'currency' => 'USD']);

        $personId = DB::table('people')->insertGetId(['business_id' => $bizA->id]);

        $ownJobId = DB::table('work_orders')->insertGetId([
            'business_id' => $bizA->id,
            'title' => 'Own tap',
            'scheduled_at' => '2025-01-01 10:00:00',
            'created_at' => '2025-01-01 09:00:00',
            'updated_at' => '2025-01-01 09:00:00',
            'person_id' => $personId,
        ]);

        $action = new JobStateAction;
        $action->updateState($bizA->id, $ownJobId, 3, 'completed');

        Event::assertDispatched(JobCompleted::class, function ($e) use ($personId) {
            $this->assertSame($personId, $e->personId, 'Tenant A must read its own person_id');
            return true;
        });
    }
}
PHP;

$content = preg_replace('/    public function test_defect_arm_cannot_read_cross_tenant_person_id\(\): void\n    \{.*?\}$/s', $new, $content);
file_put_contents('tests/Modules/X-171/X171Test.php', $content);
