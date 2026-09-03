<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search1 = "throw \$this->todo('poll until the carrier returns a real number');";
$replace1 = <<<'PHP'
        $this->drainQueue();
        $number = \Illuminate\Support\Facades\DB::table('phone_numbers')->where('business_id', $tenant['id'])->first();
        return $number ? $number->e164 : '';
PHP;
$content = str_replace($search1, $replace1, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
