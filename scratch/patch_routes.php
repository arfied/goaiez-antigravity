<?php
$file = __DIR__.'/../app/app/Console/Commands/SurfacesGenerateCommand.php';
$content = file_get_contents($file);
$replacement = <<<'PHP'
    private function generateRoutesFile(string $modDir, string $modSlug, array $tenantRoutes, array $operatorRoutes): void
    {
        $content = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n";
        
        if (!empty($tenantRoutes)) {
            $content .= "app('router')->aliasMiddleware('tenant.role', function (\\\$request, \\\$next) {\n";
            $content .= "    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);\n";
            $content .= "    return \\\$next(\\\$request);\n";
            $content .= "});\n\n";
            $content .= "Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/$modSlug')->group(function () {\n";
            $content .= implode("\n", $tenantRoutes) . "\n";
            $content .= "});\n\n";
        }
        
        if (!empty($operatorRoutes)) {
            $content .= "Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/$modSlug')->group(function () {\n";
            $content .= implode("\n", $operatorRoutes) . "\n";
            $content .= "});\n\n";
        }
        
        file_put_contents($modDir . '/routes.generated.php', $content);
    }
PHP;

$content = preg_replace('/private function generateRoutesFile.*?file_put_contents\(\$modDir \. \'\/routes\.generated\.php\', \$content\);\n    }/s', $replacement, $content);
file_put_contents($file, $content);
