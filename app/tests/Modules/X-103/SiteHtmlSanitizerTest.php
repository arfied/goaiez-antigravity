<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\SiteHtmlSanitizer;
use PHPUnit\Framework\TestCase;

class SiteHtmlSanitizerTest extends TestCase
{
    private const ATTACK = <<<'HTML'
<style>@import url(https://evil.test/x.css); .hero{background:url("https://evil.test/a.png") center;color:red} .x{width:expression(alert(1))} .y{background:u\72 l(https://evil.test/y.png)} .z{background-image:image-set("https://evil.test/z.png" 1x)}</style>
<main class="page">
  <!-- a comment 7501 -->
  <section class="hero" style="background-image:url(https://evil.test/b.png);padding:2rem" onclick="alert(1)">
    <h1 onmouseover="x()">Asul &amp; Blue 7502</h1>
    <a href="tel:+442070007503" class="btn">Call</a>
    <a href="javascript:alert(1)">bad</a>
    <a href="  JaVaScRiPt:alert(2)">bad2</a>
    <img src="[[image:1]]" alt="hero 7504" onerror="alert(1)">
    <img src="https://evil.test/c.png" alt="external 7505">
    <script>alert(1)</script>
    <iframe src="https://evil.test/frame"></iframe>
    <form action="https://evil.test/form"><input name="x"></form>
    <svg viewBox="0 0 24 24" fill="none" onload="alert(1)"><path d="M1 1L2 2" stroke="currentColor"/><use href="#x"/></svg>
    <div style="color:blue">kept 7506</div>
    <a href="https://example.com/7507">ext</a>
  </section>
</main>
<style>.after-7508{color:green}</style>
HTML;

    private function all(): string
    {
        $out = (new SiteHtmlSanitizer)->clean(self::ATTACK);

        return $out['style']."\n".$out['html'];
    }

    public function test_it_removes_every_way_to_run_code(): void
    {
        $all = strtolower($this->all());

        foreach (['<script', 'onclick', 'onmouseover', 'onerror', 'onload', 'javascript', '<iframe', '<form', '<input', '<use', 'expression('] as $needle) {
            $this->assertStringNotContainsString($needle, $all, $needle);
        }
    }

    public function test_it_removes_every_way_to_load_from_outside(): void
    {
        $all = strtolower($this->all());

        foreach (['url(', '@import', 'image-set(', 'src="https://evil', 'external 7505', 'a comment 7501'] as $needle) {
            $this->assertStringNotContainsString($needle, $all, $needle);
        }
    }

    public function test_it_keeps_the_design(): void
    {
        $all = $this->all();

        foreach (['class="hero"', 'padding:2rem', 'Asul &amp; Blue 7502', 'href="tel:+442070007503"', 'src="[[image:1]]"', 'hero 7504', 'viewBox="0 0 24 24"', 'stroke="currentColor"', 'kept 7506', 'href="https://example.com/7507"', '.after-7508{color:green}', 'color:red'] as $needle) {
            $this->assertStringContainsString($needle, $all, $needle);
        }
    }
}
