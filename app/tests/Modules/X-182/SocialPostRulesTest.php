<?php

declare(strict_types=1);

namespace Tests\Modules\X182;

use App\Modules\X182\Domain\SocialPostRules;

it('refuses empty content with no media', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['facebook'], '   ', []);
    expect($refusals)->toContain('content_empty');
});

it('refuses unknown platform', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['twitter'], 'Hello', []);
    expect($refusals)->toContain('platform_unknown:twitter');
});

it('refuses instagram with no media', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['instagram'], 'Hello', []);
    expect($refusals)->toContain('instagram_needs_media');
});

it('refuses instagram with link in caption', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['instagram'], 'Hello https://example.com', ['https://img.com/1.jpg']);
    expect($refusals)->toContain('instagram_caption_link');
});

it('refuses non-https media', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['facebook'], 'Hello', ['http://img.com/1.jpg']);
    expect($refusals)->toContain('media_not_https');
});

it('refuses unknown media type', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['facebook'], 'Hello', ['https://img.com/1.pdf']);
    expect($refusals)->toContain('media_type_unknown');
});

it('refuses facebook with too many images', function () {
    $rules = new SocialPostRules;
    $media = array_fill(0, 11, 'https://img.com/1.jpg');
    $refusals = $rules->refusals(['facebook'], 'Hello', $media);
    expect($refusals)->toContain('facebook_too_many_images');
});

it('refuses facebook with more than one video', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['facebook'], 'Hello', ['https://img.com/1.mp4', 'https://img.com/2.mp4']);
    expect($refusals)->toContain('facebook_one_video_only');
});

it('refuses facebook mixing image and video', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['facebook'], 'Hello', ['https://img.com/1.jpg', 'https://img.com/2.mp4']);
    expect($refusals)->toContain('facebook_no_mix');
});

it('refuses instagram with too many media', function () {
    $rules = new SocialPostRules;
    $media = array_fill(0, 11, 'https://img.com/1.jpg');
    $refusals = $rules->refusals(['instagram'], 'Hello', $media);
    expect($refusals)->toContain('instagram_too_many_media');
});

it('allows clean facebook text post', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['facebook'], 'Hello world', []);
    expect($refusals)->toBeEmpty();
});

it('allows clean instagram image post', function () {
    $rules = new SocialPostRules;
    $refusals = $rules->refusals(['instagram'], 'Hello world', ['https://img.com/1.jpg']);
    expect($refusals)->toBeEmpty();
});
