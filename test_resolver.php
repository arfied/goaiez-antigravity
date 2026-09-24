<?php
require 'app/vendor/autoload.php';
$app = require_once 'app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$owner = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
$biz = \App\Models\Business::provision(['owner_user_id' => $owner->id]);
$biz->update(['industry' => 'trades']);
\App\Models\IndustryStartingPoint::updateOrCreate(['family' => 'trades'], [
    'palette' => ['surface' => '#ffffff', 'ink' => '#000000', 'primary' => '#ff0000', 'accent' => '#0000ff'],
    'type_pairing' => ['heading' => 'serif', 'body' => 'sans'],
    'section_order' => ['hero', 'about', 'gallery', 'reviews_strip', 'contact'],
]);

$resolver = app(\App\Services\Industry\IndustryResolver::class);
$res = $resolver->for($biz->id);
print_r($res);

$sp = app(\App\Services\Industry\IndustryStartingPoints::class);
print_r($sp->forBusiness($biz->id));
