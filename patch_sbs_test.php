<?php

$content = file_get_contents('app/tests/Modules/X-103/Screens/SiteBuildScreenTest.php');

$newTest = <<<'CODE'
    public function test_pick_a_look(): void
    {
        $owner = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id, 'industry' => 'trades']);
        $this->actingAs($owner);
        \App\Support\Tenancy::set($biz->id);

        \App\Models\IndustryStartingPoint::create([
            'family' => \App\Enums\IndustryFamily::Trades->value,
            'palette' => ['surface' => '#ffffff', 'ink' => '#000000', 'primary' => '#ff0000', 'accent' => '#0000ff'],
            'type_pairing' => ['heading' => 'serif', 'body' => 'sans'],
            'section_order' => ['hero', 'about', 'gallery', 'reviews_strip', 'contact'],
        ]);

        $this->get(route('x-103.site-build'))
            ->assertOk()
            ->assertSee('3. Pick a look')
            ->assertSee('Draft the site first');

        \App\Modules\X103\Models\Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'H'],
                ['type' => 'about', 'text' => 'A'],
                ['type' => 'reviews_strip', 'items' => []]
            ],
            'is_published' => false,
        ]);

        $this->get(route('x-103.site-build'))
            ->assertSee('Look A')
            ->assertSee('Pick this');

        \Livewire\Livewire::actingAs($owner)->test(\App\Modules\X103\Ui\SiteBuild::class)
            ->call('chooseLook', 'c')
            ->assertSet('success', fn ($s) => str_starts_with((string) $s, 'Look C picked'));

        $biz->refresh();
        $this->assertSame('c', $biz->site_variant);

        $home = \App\Modules\X103\Models\Page::where('business_id', $biz->id)->where('slug', 'home')->first();
        $this->assertSame('reviews_strip', $home->draft_blocks[1]['type']);

        $manager = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Manager]);
        \Livewire\Livewire::actingAs($manager)->test(\App\Modules\X103\Ui\SiteBuild::class)
            ->call('chooseLook', 'c')
            ->assertForbidden();
    }
}
CODE;

$content = str_replace("}\n", "\n".$newTest, $content);
// wait, the last "}" in file is the class end, but there could be empty lines. We replace the last occurrence.
$content = preg_replace('/\}\s*$/', $newTest, $content);

file_put_contents('app/tests/Modules/X-103/Screens/SiteBuildScreenTest.php', $content);
