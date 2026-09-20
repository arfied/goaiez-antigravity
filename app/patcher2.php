<?php
$files = [
    'tests/Modules/X-140/X140Test.php',
    'tests/Modules/X-148/X148Test.php',
    'tests/Modules/X-154/X154Test.php',
    'tests/Modules/X-160/X160Test.php',
    'tests/Modules/X-161/X161Test.php',
    'tests/Modules/X-173/X173Test.php',
    'tests/Modules/X-189/X189Test.php',
    'tests/Modules/X-195/X195Test.php',
    'tests/Modules/X-196/X196Test.php',
    'tests/Modules/X-202/X202Test.php',
    'tests/Modules/X-209/X209Test.php',
    'tests/Modules/X-212/X212Test.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $lines = file($file);
    $out = [];
    $docblock = [];
    $inDoc = false;
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        
        if (!$inDoc && strpos($line, '/**') !== false) {
            $inDoc = true;
            $docblock = [$line];
            if (strpos($line, '*/') !== false) {
                $inDoc = false;
            }
            continue;
        }
        
        if ($inDoc) {
            $docblock[] = $line;
            if (strpos($line, '*/') !== false) {
                $inDoc = false;
            }
            continue;
        }
        
        if (preg_match('/public function test_[a-zA-Z0-9_]+\(\): void/', $line)) {
            $j = $i;
            $hasAssert = false;
            while ($j < count($lines) && $j < $i + 5) {
                if (strpos($lines[$j], 'assertTrue(true)') !== false) {
                    $hasAssert = true;
                    break;
                }
                $j++;
            }
            
            if ($hasAssert) {
                $out = array_merge($out, $docblock);
                $docblock = [];
                // skip until '}' alone on a line or just a simple check
                while ($i < count($lines)) {
                    if (strpos($lines[$i], '}') === 4) {
                        break;
                    }
                    $i++;
                }
                continue;
            }
        }
        
        if (!empty($docblock)) {
            $out = array_merge($out, $docblock);
            $docblock = [];
        }
        
        $out[] = $line;
    }
    
    if (!empty($docblock)) {
        $out = array_merge($out, $docblock);
    }
    
    file_put_contents($file, implode("", $out));
    echo "Patched $file\n";
}
