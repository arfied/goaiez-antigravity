<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function confirmPrice(array \$tenant, string \$sku, int \$amountMinor): void
    {
        DB::table('price_book_items')->updateOrInsert(
            ['business_id' => \$tenant['id'], 'service_name' => \$sku],
            ['price_cents' => \$amountMinor, 'tax_rate_pct' => 0, 'is_sample' => false]
        );
        DB::table('facts')->updateOrInsert(
            ['business_id' => \$tenant['id'], 'key' => "service.{\$sku}.price"],
            ['value' => '$'.number_format(\$amountMinor / 100, 2), 'is_valid' => true]
        );
    }
PHP;
$replace = <<<PHP
    private function confirmPrice(array \$tenant, string \$sku, int \$amountMinor): void
    {
        \App\Support\Tenancy::set(\$tenant['id']);
        app(\App\Services\Assistant\PriceBook::class)->set(\$sku, \$amountMinor);
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
