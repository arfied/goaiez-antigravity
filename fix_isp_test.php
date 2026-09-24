<?php
$orig = shell_exec('git show HEAD:app/tests/Feature/Industry/IndustryStartingPointsTest.php');

$newTest = <<<CODE
    public function test_for_and_variants(): void
    {
        \$resolver = app(\\App\\Services\\Industry\\IndustryResolver::class);
        \$sp = app(\\App\\Services\\Industry\\IndustryStartingPoints::class);

        \$base = \$sp->for(\\App\\Enums\\IndustryFamily::Trades);
        \$this->assertSame(\$base['palette']['surface'], \$base['palette']['card']);

        \$b = \$sp->variant(\$base, 'b');
        \$this->assertSame(\$base['type_pairing']['heading'], \$b['type_pairing']['body']);
        \$this->assertSame(\$base['type_pairing']['body'], \$b['type_pairing']['heading']);
        \$this->assertNotSame(\$base['palette']['surface'], \$b['palette']['surface']);

        \$c = \$sp->variant(\$base, 'c');
        \$this->assertSame(\$base['palette']['primary'], \$c['palette']['accent']);
        \$this->assertSame(\$base['palette']['accent'], \$c['palette']['primary']);
        \$this->assertSame('hero', \$c['section_order'][0]);
        \$this->assertSame('reviews_strip', \$c['section_order'][1]);

        \$z = \$sp->variant(\$base, 'z');
        \$this->assertSame(\$base, \$z);

        \$biz = \\Tests\\TestCase::provisionTenant(['industry' => \\App\\Enums\\IndustryFamily::Trades->value]);
        \$biz->update(['site_variant' => 'c']);

        \$forBiz = \$sp->forBusiness(\$biz->id);
        \$this->assertSame(\$c['palette']['primary'], \$forBiz['palette']['primary']);
        \$this->assertSame(\$c['palette']['accent'], \$forBiz['palette']['accent']);
    }
}
CODE;

$content = str_replace("}\n", "\n" . $newTest, $orig);
$content = preg_replace('/\}\s*$/', $newTest, $orig);

file_put_contents('app/tests/Feature/Industry/IndustryStartingPointsTest.php', $content);
