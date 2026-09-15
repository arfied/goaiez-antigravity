<?php
$content = file_get_contents('app/tests/Modules/X-01/X01Test.php');
$search = <<<'CODE'
        $customer = Customer::factory()->create(['business_id' => $biz->id, 'name' => 'Ghosty']);

        // Create an unrelated Person that happens to share the Customer's ID.
        $person = Person::create([
            'id' => $customer->id,
            'business_id' => $biz->id,
            'first_name' => 'Unrelated',
            'email' => 'unrelated@example.com',
        ]);
CODE;
$replace = <<<'CODE'
        $customer = Customer::factory()->create(['id' => 999999, 'business_id' => $biz->id, 'name' => 'Ghosty']);

        // Create an unrelated Person that happens to share the Customer's ID.
        $person = Person::create([
            'id' => 999999,
            'business_id' => $biz->id,
            'first_name' => 'Unrelated',
            'email' => 'unrelated@example.com',
        ]);
CODE;
$content = str_replace($search, $replace, $content);
file_put_contents('app/tests/Modules/X-01/X01Test.php', $content);
