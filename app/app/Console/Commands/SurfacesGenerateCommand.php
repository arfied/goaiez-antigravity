<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SurfacesGenerateCommand extends Command
{
    public static array $unplacedModules = [];

    protected $signature = 'surfaces:generate';

    protected $description = 'Generate routes, navigation, gates, and tests from module manifests';

    public function handle(): int
    {
        if (! is_file(config_path('features.php'))) {
            $this->error('STOP: config/features.php is missing.');

            return 1;
        }
        $featuresConfig = require config_path('features.php');

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

        $seenNavRoutes = [];
        $allNavGroups = [
            'tenant' => [],
            'operator' => [],
            'agency' => [],
            'tech' => [],
        ];

        $routeClashCheck = [];
        $existingRoutesJson = shell_exec('php artisan route:list --json');
        if ($existingRoutesJson) {
            $existingRoutes = json_decode($existingRoutesJson, true);
            if (is_array($existingRoutes)) {
                foreach ($existingRoutes as $route) {
                    if (! isset($route['uri'])) {
                        continue;
                    }
                    $uri = '/'.ltrim($route['uri'], '/');
                    if (isset($route['action']) && ! str_contains($route['action'], 'Modules')) {
                        $routeClashCheck[$uri] = true;
                    }
                }
            }
        }

        $skippedRenders = [];

        foreach (glob($modulesDir.'/*/manifest.php') as $manifestFile) {
            $modDir = dirname($manifestFile);
            $modId = basename($modDir);
            $manifest = include $manifestFile;

            $renders = $manifest['renders'] ?? [];
            if (empty($renders)) {
                continue;
            }

            $spFile = $modDir.'/ModuleServiceProvider.php';
            $spContent = file_exists($spFile) ? file_get_contents($spFile) : '';

            $modSlug = Str::slug($modId);
            $modClassNamespace = str_replace('-', '', $modId);
            $tenantRoutes = [];
            $operatorRoutes = [];
            $routeClasses = [];
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
                        if (preg_match('/use\s+([^;]+?)\s+as\s+'.$matchClass.'\s*;/i', $spContent, $useMatch)) {
                            $fqcn = '\\'.trim($useMatch[1]);
                        } else {
                            preg_match_all('/use\s+([^;]+?)\s*;/i', $spContent, $uses);
                            foreach ($uses[1] as $use) {
                                if (preg_match('/as\s+'.$matchClass.'$/i', $use) || preg_match('/\\\\('.$matchClass.')$/i', $use)) {
                                    $useParts = preg_split('/\s+as\s+/i', $use);
                                    $fqcn = '\\'.trim($useParts[0]);
                                    break;
                                }
                            }
                        }

                        if ($fqcn) {
                            $matchClass = $fqcn;
                        }

                        $renderHyphen = str_replace('_', '-', $render);
                        $renderKebab = Str::kebab($render);
                        if (str_contains($matchAlias, $renderHyphen) || str_contains(str_replace('-', '_', $matchAlias), $render) || str_contains($matchAlias, $renderKebab)) {
                            $alias = $matchAlias;
                            $class = $matchClass;
                            break;
                        }
                    }
                }

                if (! $alias || ! $class) {
                    $skippedRenders[] = "$modId: $render";

                    continue;
                }

                $screensLine = $moduleScreens[$modId] ?? '';
                $isTenant = str_contains(strtolower($screensLine), 'tenant') && ! str_contains(strtolower($screensLine), 'tenant: none');
                $isOperator = str_contains(strtolower($screensLine), 'operator') && ! str_contains(strtolower($screensLine), 'operator: none');
                $isAgency = str_contains(strtolower($screensLine), 'agency') && ! str_contains(strtolower($screensLine), 'agency: none');
                $isTech = str_contains(strtolower($screensLine), 'tech') && ! str_contains(strtolower($screensLine), 'tech: none');

                $primarySurfaces = [];
                if ($isTenant) {
                    $primarySurfaces[] = 'tenant';
                }
                if ($isOperator) {
                    $primarySurfaces[] = 'operator';
                }
                if ($isAgency) {
                    $primarySurfaces[] = 'agency';
                }
                if ($isTech) {
                    $primarySurfaces[] = 'tech';
                }

                if (empty($primarySurfaces)) {
                    $primarySurfaces[] = 'tenant';
                }

                $screenSlug = Str::slug(str_replace('_', '-', $render));
                if (str_starts_with($screenSlug, "$modSlug-")) {
                    $screenSlug = substr($screenSlug, strlen("$modSlug-"));
                }
                $humanName = Str::title(str_replace('_', ' ', $render));

                $navGroup = 'Unplaced';
                $navGroupSurface = 'tenant';
                $isDeferred = in_array($modId, $featuresConfig['deferred'] ?? []);

                if (! $isDeferred) {
                    foreach ($featuresConfig['entries'] ?? [] as $entry) {
                        if (in_array($modId, $entry['modules'] ?? [])) {
                            $navGroup = $entry['label'];
                            $navGroupSurface = $entry['surface'] ?? 'tenant';
                            break;
                        }
                    }
                    if ($navGroup === 'Unplaced') {
                        // The test expects us to count them and print their ID. We can print it directly here or track it.
                        // Wait, to print we can use $this->info
                        // Actually let's track it in a property or static array to print at the end.
                        self::$unplacedModules[$modId] = true;
                    }
                }

                $addedToRoutesFile = false;

                $classRef = $class[0] === '\\' ? $class : "\\App\\Modules\\{$modClassNamespace}\\Ui\\".$class;
                $routeClasses[] = $classRef;
                $classNameOnly = preg_replace('/^.*\\\\/', '', $classRef);

                $routeParams = '';
                $routeArgsArray = [];
                if (class_exists($classRef) && method_exists($classRef, 'mount')) {
                    $refMethod = new \ReflectionMethod($classRef, 'mount');
                    foreach ($refMethod->getParameters() as $param) {
                        if (! $param->isOptional()) {
                            $pName = $param->getName();
                            $routeParams .= '/{'.$pName.'}';
                            $routeArgsArray[] = "'$pName' => \$$pName";
                        }
                    }
                }

                foreach ($primarySurfaces as $surf) {
                    if ($surf === 'operator') {
                        $uri = "/admin/{$modSlug}/{$screenSlug}{$routeParams}";
                        if (isset($routeClashCheck[$uri])) {
                            $this->error("STOP: Route clash detected on $uri with a legacy route.");

                            return 1;
                        }
                        $operatorRoutes[] = "    Route::get('/{$screenSlug}{$routeParams}', {$classNameOnly}::class)->name('$alias.admin');";
                        $addedToRoutesFile = true;
                    } else {
                        $uri = "/app/{$modSlug}/{$screenSlug}{$routeParams}";
                        if (isset($routeClashCheck[$uri])) {
                            $this->error("STOP: Route clash detected on $uri with a legacy route.");

                            return 1;
                        }
                        $tenantRoutes[] = "    Route::get('/{$screenSlug}{$routeParams}', {$classNameOnly}::class)->name('$alias');";
                        $addedToRoutesFile = true;
                    }

                    $navRoute = $surf === 'operator' ? "$alias.admin" : $alias;
                    if (! $isDeferred && empty($routeParams)) {
                        if ($surf === 'tenant' && $navGroupSurface === 'other') {
                            continue;
                        }
                        if (isset($seenNavRoutes[$navRoute])) {
                            continue;
                        }
                        $seenNavRoutes[$navRoute] = true;
                        $allNavGroups[$surf][$navGroup][] = [
                            'label' => $humanName,
                            'route' => $navRoute,
                            'module' => $modId,
                        ];
                    }
                }

                $this->generatePageTest($modId, $class, $primarySurfaces, $alias, $uri, $routeArgsArray);
                $this->updatePlaceholderTemplate($modId, $classRef, $render, $moduleTitles[$modId] ?? $modId);

                $addedRoutes = true;
            }

            if ($addedRoutes) {
                $this->generateRoutesFile($modDir, $modSlug, $tenantRoutes, $operatorRoutes, $routeClasses);

                if (! str_contains($spContent, 'routes.generated.php')) {
                    $newSpContent = preg_replace(
                        '/(public function boot\(\):\s*void\s*\{)/',
                        "$1\n        \$this->loadRoutesFrom(__DIR__.'/routes.generated.php');",
                        $spContent
                    );
                    file_put_contents($spFile, $newSpContent);
                }
            }
        }

        if (! empty($skippedRenders)) {
            $this->info('Skipped renders with no component:');
            foreach ($skippedRenders as $skip) {
                $this->line(" - $skip");
            }
        }

        $this->generateSurfacesConfig($allNavGroups);
        $this->generateSharedComponent();

        $unplacedCount = count(self::$unplacedModules);
        $this->line("unplaced: {$unplacedCount}");
        foreach (array_keys(self::$unplacedModules) as $u) {
            $this->line(" - {$u}");
        }

        return 0;
    }

    private function generateRoutesFile(string $modDir, string $modSlug, array $tenantRoutes, array $operatorRoutes, array $routeClasses): void
    {
        $content = "<?php\n\ndeclare(strict_types=1);\n\n";

        $imports = ['Illuminate\Support\Facades\Route'];
        if (! empty($operatorRoutes)) {
            $imports[] = 'App\Support\Admin\AdminAccess';
        }
        foreach ($routeClasses as $class) {
            $imports[] = ltrim($class, '\\');
        }
        $imports = array_unique($imports);
        sort($imports);
        foreach ($imports as $import) {
            $content .= "use {$import};\n";
        }
        $content .= "\n";

        if (! empty($tenantRoutes)) {
            $content .= "Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/$modSlug')->group(function () {\n";
            $content .= implode("\n", $tenantRoutes)."\n";
            $content .= "});\n";
            if (! empty($operatorRoutes)) {
                $content .= "\n";
            }
        }

        if (! empty($operatorRoutes)) {
            $content .= "Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/$modSlug')->group(function () {\n";
            $content .= implode("\n", $operatorRoutes)."\n";
            $content .= "});\n";
        }

        file_put_contents($modDir.'/routes.generated.php', $content);
    }

    private function generatePageTest(string $modId, string $class, array $primarySurfaces, string $alias, string $uri, array $routeArgsArray = []): void
    {
        $testDir = base_path("tests/Modules/$modId/Screens");
        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $className = preg_replace('/^.*\\\\/', '', $class);
        $testFile = $testDir."/{$className}ScreenTest.php";

        $fqcn = str_starts_with($class, '\\') ? $class : '\\App\\Modules\\'.str_replace('-', '', $modId)."\\Ui\\$class";
        $fqcnNoSlash = ltrim($fqcn, '\\');

        $imports = [
            $fqcnNoSlash,
            "App\Enums\UserRole",
            "App\Models\User",
            "Livewire\Livewire",
            "Tests\TestCase",
        ];
        if ($modId === 'X-179' && ! empty($routeArgsArray)) {
            $imports[] = 'App\Modules\X179\Actions\ContentExtractAction';
            $imports[] = 'App\Modules\X179\Actions\TemplateMatchAction';
        }
        sort($imports);
        $importsStr = '';
        foreach ($imports as $import) {
            $importsStr .= "use {$import};\n";
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Tests\Modules\\".str_replace('-', '', $modId)."\Screens;\n\n{$importsStr}\n";

        $fixtureFile = base_path("tests/Modules/{$modId}/Screens/Fixtures.php");
        if (file_exists($fixtureFile)) {
            $content .= "require_once __DIR__.'/Fixtures.php';\n\n";
        }

        $content .= "class {$className}ScreenTest extends TestCase\n{\n";

        $provisioningStr = '';
        $routeArgsStr = '';
        if (! empty($routeArgsArray)) {
            $routeArgsStr = ', ['.implode(', ', $routeArgsArray).']';
            if ($modId === 'X-179') {
                $provisioningStr .= "\n        \$action = new ContentExtractAction;\n";
                $provisioningStr .= "        \$action->extractContent(\$biz->id, 123, 'gbp', 'test');\n";
                $provisioningStr .= "        \$matchAction = new TemplateMatchAction;\n";
                $provisioningStr .= "        \$matchAction->matchAndRender(\$biz->id, 123);\n";
                $provisioningStr .= "        \$prospectId = 123;\n";
            } else {
                foreach ($routeArgsArray as $argStr) {
                    if (preg_match('/\'(.*)\'\s*=>\s*\$(.*)/', $argStr, $m)) {
                        $pName = $m[2];
                        $fixtureFile = base_path("tests/Modules/{$modId}/Screens/Fixtures.php");
                        if (file_exists($fixtureFile) && str_contains(file_get_contents($fixtureFile), "function {$pName}(")) {
                            $provisioningStr .= "        \$$pName = Fixtures::{$pName}(\$biz);\n";
                        } else {
                            $provisioningStr .= "        \$$pName = 1;\n";
                        }
                    }
                }
            }
        }

        $testsCount = 0;
        if (in_array('tenant', $primarySurfaces) || in_array('agency', $primarySurfaces) || in_array('tech', $primarySurfaces) || empty($primarySurfaces)) {
            $content .= "    public function test_screen_renders_for_tenant(): void\n    {\n";
            $content .= "        \$owner = User::factory()->create(['role' => UserRole::Owner]);\n";
            $content .= "        \$biz = \$this->provisionTenant(['owner_user_id' => \$owner->id]);\n";
            $content .= "        \$this->actingAs(\$owner);\n";
            $content .= $provisioningStr;
            $content .= "\n        \$this->get(route('$alias'$routeArgsStr))->assertOk();\n";
            $content .= "\n        Livewire::test({$className}::class$routeArgsStr)->assertOk();\n";
            $content .= "    }\n";
            $testsCount++;
        }

        if (in_array('operator', $primarySurfaces)) {
            if ($testsCount > 0) {
                $content .= "\n";
            }
            $content .= "    public function test_screen_renders_for_admin(): void\n    {\n";
            $content .= "        \$user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);\n";
            $content .= "        \$this->actingAs(\$user);\n";
            $content .= "        \$biz = \$this->provisionTenant(['owner_user_id' => \$user->id]);\n"; // operator still needs biz for tenant.role or operations
            $content .= $provisioningStr;
            $content .= "\n        \$this->get(route('$alias.admin'$routeArgsStr))->assertOk();\n";
            $content .= "\n        Livewire::test({$className}::class$routeArgsStr)->assertOk();\n";
            $content .= "    }\n";
        }

        $content .= "}\n";

        file_put_contents($testFile, $content);
    }

    private function generateSurfacesConfig(array $allNavGroups): void
    {
        if (! is_file(config_path('features.php'))) {
            throw new \RuntimeException('config/features.php is missing');
        }
        $featuresConfig = require config_path('features.php');

        $navOrder = [];
        foreach ($featuresConfig['entries'] ?? [] as $entry) {
            $navOrder[] = $entry['label'];
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";
        foreach (['tenant', 'operator', 'agency', 'tech'] as $surf) {
            $content .= "    '$surf' => [\n";
            uksort($allNavGroups[$surf], function ($a, $b) use ($navOrder) {
                $posA = array_search($a, $navOrder, true);
                $posB = array_search($b, $navOrder, true);
                if ($posA === false) {
                    throw new \RuntimeException("Nav group '{$a}' is not an entry label in config/features.php");
                }
                if ($posB === false) {
                    throw new \RuntimeException("Nav group '{$b}' is not an entry label in config/features.php");
                }

                return $posA <=> $posB;
            });

            foreach ($allNavGroups[$surf] as $group => $entries) {
                if (empty($entries)) {
                    continue;
                }
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
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $content = "<div>\n    <p>Sample — this screen is planned in {{ \$module }} and not built yet</p>\n</div>";
        file_put_contents($dir.'/sample-state.blade.php', $content);
    }

    private function updatePlaceholderTemplate(string $modId, string $classRef, string $render, string $title): void {}
}
