<?php
$content = file_get_contents('app/tests/Journeys/JourneyHarness.php');

$search = <<<PHP
    private function invoiceStatus(array \$invoice): string
    {
        throw \$this->todo('read the invoice status from its owning module');
    }
PHP;
$replace = <<<PHP
    private function invoiceStatus(array \$invoice): string
    {
        \$sub = \Illuminate\Support\Facades\DB::table('invoices')->where('id', \$invoice['id'])->first();
        return \$sub->status;
    }
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/tests/Journeys/JourneyHarness.php', $content);
