import re

def patch(filename):
    with open(filename, 'r') as f:
        content = f.read()
    
    # replace expect(VAR)->toBe(NUMBER, MSG) with expect(VAR)->toBe(VAR, MSG); echo "VAR=$VAR\n";
    # but some have no msg.
    # Actually just add echo before the expect
    
    content = re.sub(r'expect\(\$(total|seam|own|legal|illegal)\)->toBe\(', r'echo "\1=$$""\1\n"; expect($\1)->toBe(', content)
    content = re.sub(r'expect\(count\(\$(invisible|withLayout|withoutLayout|unbuilt|built)\)\)->toBe\(', r'echo "\1=" . count($\1) . "\n"; expect(count($\1))->toBe(', content)
    
    with open(filename, 'w') as f:
        f.write(content)

patch("app/tests/Feature/Architecture/HeadingSeamTest.php")
patch("app/tests/Feature/Architecture/OwnerNavTest.php")
patch("app/tests/Feature/Architecture/SampleStateModuleTest.php")
