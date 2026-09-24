<?php

$content = file_get_contents('app/app/Modules/X-103/Ui/SiteBuild.php');

// Add success property
$content = str_replace(
    'public ?string $error = null;',
    "public ?string \$error = null;\n\n    public ?string \$success = null;",
    $content
);

// Add chooseLook function
$chooseLookFn = <<<'CODE'
    public function chooseLook(string $variant, \App\Services\Industry\IndustryStartingPoints $sp, \App\Services\Industry\IndustryResolver $ir, PageReadAction $pages): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        if (! in_array($variant, \App\Services\Industry\IndustryStartingPoints::VARIANTS, true)) {
            $this->error = 'Invalid look.';
            return;
        }

        \App\Models\Business::query()->whereKey($this->businessId)->update(['site_variant' => $variant]);

        $home = $pages->homeFor($this->businessId);
        if ($home && is_array($home->draft_blocks)) {
            $family = $ir->for($this->businessId)['family'];
            $variantTokens = $sp->variant($sp->for($family), $variant);
            $home->draft_blocks = \App\Modules\X103\Domain\SectionOrder::apply($home->draft_blocks, $variantTokens['section_order']);
            $home->save();
        }

        $this->success = 'Look '.strtoupper($variant).' picked — your pages follow it from the next draft and the next publish.';
    }
CODE;

$content = str_replace(
    'public function runBuild',
    $chooseLookFn."\n\n    public function runBuild",
    $content
);

// Modify render function to include previews and chosenVariant
$content = preg_replace(
    '/return view\(\'x-103::site-build\', \[\n(.*?)\]\);/s',
    "return view('x-103::site-build', [\n$1            'previews' => app(\\App\\Modules\\X103\\Actions\\SitePreviewAction::class)->handle(\$this->businessId),\n            'chosenVariant' => \\App\\Models\\Business::query()->whereKey(\$this->businessId)->value('site_variant') ?? 'a',\n        ]);",
    $content
);

file_put_contents('app/app/Modules/X-103/Ui/SiteBuild.php', $content);
