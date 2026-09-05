<?php
$file = __DIR__.'/../app/app/Console/Commands/SurfacesGenerateCommand.php';
$content = file_get_contents($file);

$replacement = <<<'PHP'
                if (preg_match_all($pattern, $spContent, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $matchAlias = $match[1];
                        $matchClass = $match[2];
                        
                        // Check for use alias
                        if (preg_match('/use\s+([^;]+?)\s+as\s+' . $matchClass . '\s*;/i', $spContent, $useMatch)) {
                            // $useMatch[1] is like App\Modules\X01\Ui\Person
                            $matchClass = '\\' . trim($useMatch[1]);
                        } elseif (preg_match('/use\s+([^;]+?\\\([^;\\\\]+?))\s*;/i', $spContent, $useMatch)) {
                            // It's much simpler to just search for a use statement ending in $matchClass
                            preg_match_all('/use\s+([^;]+?)\s*;/i', $spContent, $uses);
                            foreach ($uses[1] as $use) {
                                if (preg_match('/as\s+' . $matchClass . '$/i', $use) || preg_match('/\\\\' . $matchClass . '$/i', $use)) {
                                    $useParts = preg_split('/\s+as\s+/i', $use);
                                    $matchClass = '\\' . trim($useParts[0]);
                                    break;
                                }
                            }
                        }

                        $renderHyphen = str_replace('_', '-', $render);
                        if (str_contains($matchAlias, $renderHyphen) || str_contains(str_replace('-', '_', $matchAlias), $render)) {
                            $alias = $matchAlias;
                            $class = $matchClass;
                            break;
                        }
                    }
                }
PHP;

$content = preg_replace('/if \(preg_match_all\(\$pattern, \$spContent, \$matches, PREG_SET_ORDER\)\) \{.*?\}[\s\n]*\}/s', $replacement, $content);
file_put_contents($file, $content);
