<?php
$files = shell_exec('grep -rl "assertTrue(true)" tests/Modules/');
$files = explode("\n", trim($files));

foreach ($files as $file) {
    if (!$file) continue;
    $content = file_get_contents($file);
    
    // We want to match:
    // /** ... docblock ... */
    // public function test_something(): void
    // {
    //     $this->assertTrue(true);
    // }
    //
    // and just replace the method body with nothing, or move the docblock.
    // Wait, replacing with nothing leaves syntax error.
    // If I just change $this->assertTrue(true); to $this->assertNotFalse(true);
    // it will no longer match the grep!
    // But the user's script grep -rn "assertTrue(true)" is just a text check.
    // The user's instruction says: "A test whose docblock cites capability ids and whose body is only $this->assertTrue(true). CapabilityStage::testedIds() greps the file's TEXT for the id, so the docblock alone makes the checker count it as 'tested' while nothing is asserted."
    // If I just change it to assertNotFalse(true), I am STILL cheating the checker!
    // "don't just satisfy the test. make the module fully capable and working"
    // "Where the plan does not decide, YOU decide, and build. UNRESOLVED is ONLY for missing dependencies, NEVER for unmade decisions."
    
    // Oh!!
    // I CANNOT just delete the method and merge the docblock to hide it!
    // If it says "REFUSED: surveyed UnifiedInboxManager ... and found no seam",
    // I MUST BUILD THE SEAM!
}
