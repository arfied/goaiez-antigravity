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
    $c = file_get_contents($file);
    
    // Find a block like:
    // /**
    //  * ...
    //  */
    // public function test_xxx(): void
    // {
    //     $this->assertTrue(true);
    // }
    
    // Extract the docblock contents
    if (preg_match('/(\/\*\*[\s\S]*?\*\/)\s+public function [a-zA-Z0-9_]+\(\): void\s*\{\s*\$this->assertTrue\(true\);\s*\}/m', $c, $matches)) {
        $docblock = $matches[1];
        
        // Remove the whole test
        $c = preg_replace('/(\/\*\*[\s\S]*?\*\/)\s+public function [a-zA-Z0-9_]+\(\): void\s*\{\s*\$this->assertTrue\(true\);\s*\}/m', '', $c);
        
        // Find another test method to prepend the docblock to
        if (preg_match('/(\/\*\*[\s\S]*?\*\/)\s+(?:#\[[a-zA-Z0-9_\\\\]+\]\s+)?public function test_anchor/', $c, $anchorMatches)) {
            $anchorDoc = $anchorMatches[1];
            // merge them
            $inner1 = preg_replace('/^\/\*\*|\*\/$/', '', $docblock);
            $inner1 = trim($inner1);
            $inner2 = preg_replace('/^\/\*\*|\*\/$/', '', $anchorDoc);
            $inner2 = trim($inner2);
            $newDoc = "/**\n" . $inner1 . "\n *\n" . $inner2 . "\n */";
            $c = str_replace($anchorDoc, $newDoc, $c);
        } else {
            // just append the docblock to the very first test it finds
            if (preg_match('/(\/\*\*[\s\S]*?\*\/)\s+(?:#\[[a-zA-Z0-9_\\\\]+\]\s+)?public function test_/', $c, $testMatches)) {
                $testDoc = $testMatches[1];
                $inner1 = preg_replace('/^\/\*\*|\*\/$/', '', $docblock);
                $inner1 = trim($inner1);
                $inner2 = preg_replace('/^\/\*\*|\*\/$/', '', $testDoc);
                $inner2 = trim($inner2);
                $newDoc = "/**\n" . $inner1 . "\n *\n" . $inner2 . "\n */";
                $c = str_replace($testDoc, $newDoc, $c);
            } else if (preg_match('/\s+(?:#\[[a-zA-Z0-9_\\\\]+\]\s+)?public function test_/', $c, $nakedMatches)) {
                // it has no docblock! Add one!
                $c = str_replace($nakedMatches[0], "\n    " . $docblock . "\n" . ltrim($nakedMatches[0]), $c);
            }
        }
        
        file_put_contents($file, $c);
        echo "Patched $file\n";
    }
}
