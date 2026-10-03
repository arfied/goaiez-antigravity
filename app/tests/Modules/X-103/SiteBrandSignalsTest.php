<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\SiteBrandSignals;
use Tests\TestCase;

class SiteBrandSignalsTest extends TestCase
{
    public function test_it_reads_the_theme_colour_the_most_used_colours_and_the_fonts(): void
    {
        $html = '<html><head>'
            .'<meta name="theme-color" content="#1E5AA8">'
            .'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&amp;family=Playfair+Display&amp;display=swap">'
            .'<style>body{color:#333;background:#fff} .btn{background:#e4572e;font-family:"Font Awesome 5 Free"} h1{color:#e4572e}</style>'
            .'</head><body><div style="border-color:#2a9d8f">x</div></body></html>';

        $brand = SiteBrandSignals::read($html, ['a{color:#2a9d8f;font-family: Lato, sans-serif} b{font-family:Lato} c{color:#e4572e}']);

        $this->assertSame('#1e5aa8', $brand['theme_color']);
        $this->assertSame(['#1e5aa8', '#e4572e', '#2a9d8f'], $brand['colours']);
        $this->assertSame(['Open Sans', 'Playfair Display', 'Lato'], $brand['fonts']);
    }

    public function test_greys_generic_families_and_icon_fonts_are_not_a_brand(): void
    {
        $html = '<html><head><style>body{color:#333333;background:#FFF;border-color:#abc;font-family:system-ui, sans-serif}'
            .' i{font-family:"dashicons"} b{font-family:var(--x)}</style></head><body>plain</body></html>';

        $this->assertSame([], SiteBrandSignals::read($html));
        $this->assertSame([], SiteBrandSignals::read('<html><body><p>Nothing styled</p></body></html>'));
    }

    public function test_at_most_four_colours_and_three_fonts(): void
    {
        $css = 'a{color:#ff0000;font-family:One} b{color:#00ff00;font-family:Two} c{color:#0000ff;font-family:Three}'
            .' d{color:#ff00ff;font-family:Four} e{color:#00ffff}';

        $brand = SiteBrandSignals::read('<html><body>x</body></html>', [$css]);

        $this->assertCount(SiteBrandSignals::MAX_COLOURS, $brand['colours']);
        $this->assertCount(SiteBrandSignals::MAX_FONTS, $brand['fonts']);
        $this->assertArrayNotHasKey('theme_color', $brand);
    }
}
