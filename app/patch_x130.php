<?php
$c = file_get_contents('tests/Modules/X-130/X130Test.php');

$replacement = <<<'TEXT'
     *   capabilities.php is boilerplate identical across every N row and across modules —
     *   it names other modules entirely. Nothing to assert. (R245, REV-80/REV-81)
     * [N-079] ⛔ REFUSED: `php artisan why N-079` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across modules —
     *   it names other modules entirely. Nothing to assert. (R245, REV-80/REV-81)
     * [N-081]
     * [N-082]
     * [N-083]
     * [N-085] ⛔ REFUSED: `php artisan why N-085` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across modules —
     *   it names other modules entirely. Nothing to assert. (R245, REV-80/REV-81)
     */
    public function test_demand_capabilities(): void
    {
        $engine = new \App\Modules\X130\Domain\DemandEngine();

        // [N-081, N-082, N-083] aggregate only — refuses below N tenants (N=5)
        $refused = $engine->query(4);
        $this->assertEquals('refused', $refused['status']);
        $this->assertEquals('below_n', $refused['reason']);

        $allowed = $engine->query(5);
        $this->assertEquals('aggregate_only', $allowed['status']);
    }
TEXT;

$c = preg_replace('/     \*   capabilities\.php is boilerplate identical across every N row and across modules —.*?    public function test_demand_capabilities\(\): void\s+\{\s+\$this->assertTrue\(true\);\s+\}/s', $replacement, $c);
file_put_contents('tests/Modules/X-130/X130Test.php', $c);
