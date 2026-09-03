<?php
$content = file_get_contents('app/app/Modules/C-Reviews/ModuleServiceProvider.php');

$search = <<<PHP
    public function boot(): void
    {
PHP;
$replace = <<<PHP
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\X171\Events\JobCompleted::class,
            \App\Modules\CReviews\Listeners\RequestReviewOnJobCompleted::class
        );
PHP;
$content = str_replace($search, $replace, $content);

file_put_contents('app/app/Modules/C-Reviews/ModuleServiceProvider.php', $content);
