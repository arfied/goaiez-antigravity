<?php
$code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SurfacesGenerateCommand extends Command
{
    protected $signature = 'surfaces:generate';
    protected $description = 'Generate routes, navigation, gates, and tests from module manifests';

    public function handle(): int
    {
        $modulesDir = app_path('Modules');
        $trackerFile = base_path('GOAIEZ-TRACKER-MODULES.md');
        $planFile = base_path('GOAIEZ-MASTER-PLAN.md');

        $trackerLines = file_exists($trackerFile) ? file($trackerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $moduleGroups = [];
        foreach ($trackerLines as $line) {
            if (preg_match('/^\|\s*([C|X]-[^\|]+?)\s*\|\s*([^\|]+?)\s*\|/', $line, $m)) {
                $modId = trim($m[1]);
                $modId = preg_replace('/\s.*$/', '', $modId);
                $moduleGroups[$modId] = trim($m[2]);
            }
        }

        $planContent = file_exists($planFile) ? file_get_contents($planFile) : '';
        $planSections = preg_split('/(?=@module\s+[A-Z0-9\-]+)/', $planContent);
        $moduleScreens = [];
        $moduleTitles = [];
        foreach ($planSections as $section) {
            if (preg_match('/@module\s+([A-Z0-9\-]+)/', $section, $m)) {
                $modId = $m[1];
                if (preg_match('/^\s*\*\*SCREENS\*\*(.*?)$/m', $section, $sm)) {
                    $moduleScreens[$modId] = trim($sm[1]);
                }
                if (preg_match('/^\s*\*\*WHAT\*\*(.*?)$/m', $section, $tm)) {
                    $moduleTitles[$modId] = trim(explode('·', strip_tags(trim($tm[1])))[0]);
                } else {
                    $moduleTitles[$modId] = $modId;
                }
            }
        }

        $allNavGroups = [
            'tenant' => [],
            'operator' => [],
            'agency' => [],
            'tech' => []
        ];
        
        $routeClashCheck = [];
        $existingRoutesJson = shell_exec('php artisan route:list --json');
        if ($existingRoutesJson) {
            $existingRoutes = json_decode($existingRoutesJson, true);
            if (is_array($existingRoutes)) {
                foreach ($existingRoutes as $route) {
                    if (!isset($route['uri'])) continue;
                    $uri = '/' . ltrim($route['uri'], '/');
                    if (isset($route['action']) && !str_contains($route['action'], 'Modules')) {
                        $routeClashCheck[$uri] = true;
                    }
                }
            }
        }

        $skippedRenders = [];

        foreach (glob($modulesDir . '/*/manifest.php') as $manifestFile) {
            $modDir = dirname($manifestFile);
            $modId = basename($modDir);
            $manifest = include $manifestFile;
            
            $renders = $manifest['renders'] ?? [];
            if (empty($renders)) continue;
            
            $spFile = $modDir . '/ModuleServiceProvider.php';
            $spContent = file_exists($spFile) ? file_get_contents($spFile) : '';
            
            $modSlug = Str::slug($modId);
            $modClassNamespace = str_replace('-', '', $modId);
            $tenantRoutes = [];
            $operatorRoutes = [];
            $addedRoutes = false;

            foreach ($renders as $render) {
                $pattern = "/Livewire::component\(['\"]([^'\"]+)['\"],\s*([A-Za-z0-9_]+)::class\)/";
                $alias = null;
                $class = null;
                if (preg_match_all($pattern, $spContent, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $matchAlias = $match[1];
                        $matchClass = $match[2];
                        
                        // Alias resolution
                        $fqcn = null;
                        if (preg_match('/use\s+([^;]+?)\s+as\s+' . $matchClass . '\s*;/i', $spContent, $useMatch)) {
                            $fqcn = '\\' . trim($useMatch[1]);
                        } else {
                            preg_match_all('/use\s+([^;]+?)\s*;/i', $spContent, $uses);
                            foreach ($uses[1] as $use) {
                                if (preg_match('/as\s+' . $matchClass . '$/i', $use) || preg_match('/\\\\(' . $matchClass . ')$/i', $use)) {
                                    $useParts = preg_split('/\s+as\s+/i', $use);
                                    $fqcn = '\\' . trim($useParts[0]);
                                    break;
                                }
                            }
                        }
                        
                        if ($fqcn) {
                            $matchClass = $fqcn;
                        }

                        $renderHyphen = str_replace('_', '-', $render);
                        if (str_contains($matchAlias, $renderHyphen) || str_contains(str_replace('-', '_', $matchAlias), $render)) {
                            $alias = $matchAlias;
                            $class = $matchClass;
                            break;
                        }
                    }
                }
                
                if (!$alias || !$class) {
                    $skippedRenders[] = "$modId: $render";
                    continue;
                }
                
                $screensLine = $moduleScreens[$modId] ?? '';
                $isTenant = str_contains(strtolower($screensLine), 'tenant:') && !str_contains(strtolower($screensLine), 'tenant: none');
                $isOperator = str_contains(strtolower($screensLine), 'operator:') && !str_contains(strtolower($screensLine), 'operator: none');
                $isAgency = str_contains(strtolower($screensLine), 'agency:') && !str_contains(strtolower($screensLine), 'agency: none');
                $isTech = str_contains(strtolower($screensLine), 'tech/mobile:') && !str_contains(strtolower($screensLine), 'tech/mobile: none');
                
                $primarySurfaces = [];
                if ($isTenant) $primarySurfaces[] = 'tenant';
                if ($isOperator) $primarySurfaces[] = 'operator';
                if ($isAgency) $primarySurfaces[] = 'agency';
                if ($isTech) $primarySurfaces[] = 'tech';
                
                if (empty($primarySurfaces)) {
                    $primarySurfaces[] = 'tenant';
                }

                $screenSlug = Str::slug(str_replace('_', '-', $render));
                $humanName = Str::title(str_replace('_', ' ', $render));
                
                $rawGroup = $moduleGroups[$modId] ?? '';
                $groupMap = [
                    'money' => 'Money',
                    'reviews' => 'Reviews & QA',
                    'CRM & pipeline' => 'Customers/Inbox',
                    'surfaces & builder' => 'Website',
                    'pixel & attribution' => 'Visitors',
                    'social & content' => 'Marketing',
                    'outreach & growth' => 'Marketing',
                    'voice' => 'Calls/Inbox',
                    'telephony & channels' => 'Calls/Inbox',
                    'conversational' => 'Calls/Inbox',
                    'FSM · field' => 'Jobs & dispatch',
                    'FSM · pricebook' => 'Pricebook',
                    'FSM · money trail' => 'Money',
                    'FSM · agreements' => 'Settings',
                    'ops & agency' => 'Settings',
                    'onboarding' => 'Home',
                ];
                $navGroup = $groupMap[$rawGroup] ?? 'Settings';

                $addedToRoutesFile = false;
                
                $classRef = $class[0] === '\\' ? $class : "\\App\\Modules\\{$modClassNamespace}\\Ui\\" . $class;

                foreach ($primarySurfaces as $surf) {
                    if ($surf === 'operator') {
                        $uri = "/admin/{$modSlug}/{$screenSlug}";
                        if (isset($routeClashCheck[$uri])) {
                            $this->error("STOP: Route clash detected on $uri with a legacy route.");
                            return 1;
                        }
                        $operatorRoutes[] = "    Route::get('/" . ltrim(str_replace('/admin/', '', $uri), '/') . "', $classRef::class)->name('$alias');";
                        $addedToRoutesFile = true;
                    } else {
                        $uri = "/app/{$modSlug}/{$screenSlug}";
                        if (isset($routeClashCheck[$uri])) {
                            $this->error("STOP: Route clash detected on $uri with a legacy route.");
                            return 1;
                        }
                        if (!$addedToRoutesFile) {
                            $tenantRoutes[] = "    Route::get('/" . ltrim(str_replace('/app/', '', $uri), '/') . "', $classRef::class)->name('$alias');";
                            $addedToRoutesFile = true;
                        }
                    }

                    $allNavGroups[$surf][$navGroup][] = [
                        'label' => $humanName,
                        'route' => $alias,
                        'module' => $modId,
                    ];
                }

                $this->generatePageTest($modId, $class, $primarySurfaces, $alias, $uri);
                $this->updatePlaceholderTemplate($modId, $classRef, $render, $moduleTitles[$modId] ?? $modId);
                
                $addedRoutes = true;
            }

            if ($addedRoutes) {
                $this->generateRoutesFile($modDir, $modSlug, $tenantRoutes, $operatorRoutes);
                
                if (!str_contains($spContent, 'routes.generated.php')) {
                    $newSpContent = preg_replace(
                        '/(public function boot\(\):\s*void\s*\{)/',
                        "$1\n        \$this->loadRoutesFrom(__DIR__.'/routes.generated.php');",
                        $spContent
                    );
                    file_put_contents($spFile, $newSpContent);
                }
            }
        }

        if (!empty($skippedRenders)) {
            $this->info("Skipped renders with no component:");
            foreach ($skippedRenders as $skip) {
                $this->line(" - $skip");
            }
        }

        $this->generateSurfacesConfig($allNavGroups);
        $this->generateSharedComponent();

        return 0;
    }

    private function generateRoutesFile(string $modDir, string $modSlug, array $tenantRoutes, array $operatorRoutes): void
    {
        $content = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n";
        
        if (!empty($tenantRoutes)) {
            $content .= "app('router')->aliasMiddleware('tenant.role', function (\$request, \$next) {\n";
            $content .= "    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);\n";
            $content .= "    return \$next(\$request);\n";
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

    private function generatePageTest(string $modId, string $class, array $primarySurfaces, string $alias, string $uri): void
    {
        $testDir = base_path("tests/Modules/$modId/Screens");
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        $className = preg_replace('/^.*\\\\/', '', $class);
        $testFile = $testDir . "/{$className}ScreenTest.php";
        
        $isOperator = in_array('operator', $primarySurfaces);
        
        $content = "<?php\n\nnamespace Tests\Modules\\" . str_replace('-', '', $modId) . "\Screens;\n\n";
        $content .= "use Tests\TestCase;\n";
        $content .= "use Livewire\Livewire;\n";
        $content .= "use App\Models\User;\n";
        $content .= "use App\Enums\UserRole;\n\n";
        
        $content .= "class {$className}ScreenTest extends TestCase\n{\n";
        $content .= "    public function test_screen_renders(): void\n    {\n";
        
        if ($isOperator) {
            $content .= "        \$user = User::factory()->create(['role' => UserRole::SuperAdmin]);\n";
            $content .= "        \$this->actingAs(\$user);\n";
        } else {
            $content .= "        \$biz = \$this->provisionTenant();\n";
            $content .= "        \$owner = User::where('business_id', \$biz->id)->first();\n";
            $content .= "        \$owner->role = UserRole::Owner;\n";
            $content .= "        \$owner->save();\n";
            $content .= "        \$this->actingAs(\$owner);\n";
        }
        
        $content .= "\n        \$this->get(route('$alias'))->assertOk();\n";
        
        // Assuming $class is full name or just use the alias via route
        // For Livewire::test we need FQCN
        $fqcn = str_starts_with($class, '\\') ? $class : "\\App\\Modules\\" . str_replace('-', '', $modId) . "\\Ui\\$class";
        $content .= "\n        Livewire::test($fqcn::class)->assertOk();\n";
        
        $content .= "    }\n}\n";
        
        file_put_contents($testFile, $content);
    }

    private function generateSurfacesConfig(array $allNavGroups): void
    {
        $content = "<?php\n\nreturn [\n";
        foreach (['tenant', 'operator', 'agency', 'tech'] as $surf) {
            $content .= "    '$surf' => [\n";
            foreach ($allNavGroups[$surf] as $group => $entries) {
                if (empty($entries)) continue;
                $content .= "        '$group' => [\n";
                foreach ($entries as $entry) {
                    $content .= "            ['label' => '{$entry['label']}', 'route' => '{$entry['route']}', 'module' => '{$entry['module']}'],\n";
                }
                $content .= "        ],\n";
            }
            $content .= "    ],\n";
        }
        $content .= "];\n";
        
        file_put_contents(config_path('surfaces.generated.php'), $content);
    }

    private function generateSharedComponent(): void
    {
        $dir = resource_path('views/components/surface');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $content = "<div>\n    <p>Sample — this screen is planned in {{ \$module }} and not built yet</p>\n</div>";
        file_put_contents($dir . '/sample-state.blade.php', $content);
    }
    
    private function updatePlaceholderTemplate(string $modId, string $classRef, string $render, string $title): void
    {
        $viewFile = app_path("Modules/$modId/Ui/views/" . str_replace('_', '-', $render) . ".blade.php");
        if (file_exists($viewFile)) {
            $content = file_get_contents($viewFile);
            if (!str_contains($content, 'x-surface.sample-state')) {
                $includeLine = "<x-surface.sample-state module=\"$title\" screen=\"$render\" />\n";
                file_put_contents($viewFile, $includeLine . $content);
            }
        }
    }
}
PHP;
file_put_contents(__DIR__.'/../app/app/Console/Commands/SurfacesGenerateCommand.php', $code);
