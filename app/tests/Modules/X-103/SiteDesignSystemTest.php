<?php

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Models\Business;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Services\Industry\IndustryStartingPoints;

const CONTEXT = ['businessName' => '', 'deployHash' => 'weight', 'tenant_storage_url_prefix' => '/m/', 'form_action_base' => '/f'];

dataset('design_system_classes', [
    '.site-block--band',
    '.site-block__inner',
    '.eyebrow',
    '.lede',
    '.stack',
    '.actions',
    '.btn',
    '.btn--primary',
    '.btn--ghost',
    '.card',
    '.grid--2',
    '.grid--3',
    '.hero__grid',
    '.hero__media',
    '.media--wide',
    '.media--square',
    '.media--portrait',
]);

it('renders the design system classes exactly once', function (string $selector) {
    $biz = Business::factory()->create();
    $html = app(SiteBlockRenderer::class)->render([], ['tokens' => app(IndustryStartingPoints::class)->forBusiness($biz->id)] + CONTEXT);

    // Exactly once "as a selector" means finding the class precisely, not as part of another class name
    $matches = preg_match_all('/'.preg_quote($selector, '/').'(?![a-zA-Z0-9_-])/', $html);
    expect($matches)->toBe(1);
})->with('design_system_classes');

it('keeps old selectors surviving', function () {
    $biz = Business::factory()->create();
    $html = app(SiteBlockRenderer::class)->render([], ['tokens' => app(IndustryStartingPoints::class)->forBusiness($biz->id)] + CONTEXT);

    expect($html)->toContain('.site-block.services li')
        ->and($html)->toContain('.review-list')
        ->and($html)->toContain('.site-block.booking a')
        ->and($html)->toContain('.site-block.gallery > img');
});

it('makes no external requests in a page with a hero', function () {
    $biz = Business::factory()->create();
    $blocks = [
        ['type' => 'hero', 'headline' => 'Welcome'],
    ];
    $html = app(SiteBlockRenderer::class)->render($blocks, ['tokens' => app(IndustryStartingPoints::class)->forBusiness($biz->id)] + CONTEXT);

    expect($html)->not->toContain('http://')
        ->and($html)->not->toContain('https://')
        ->and($html)->not->toContain('@import');
});

it('has focus-visible appearing at least twice', function () {
    $biz = Business::factory()->create();
    $html = app(SiteBlockRenderer::class)->render([], ['tokens' => app(IndustryStartingPoints::class)->forBusiness($biz->id)] + CONTEXT);

    $matches = substr_count($html, ':focus-visible');
    expect($matches)->toBeGreaterThanOrEqual(2);
});

it('uses the best model for site copy', function () {
    expect(AiTask::SiteCopy->defaultModel())->toBe(AiModel::ClaudeOpus5)
        ->and(AiTask::SiteCopy->maxOutputTokens())->toBe(8192);
});
