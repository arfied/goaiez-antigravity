import re

files = {
    'app/tests/Feature/Architecture/HeadingSeamTest.php': [
        (r"expect\(\$total\)->toBe\(189", "expect($total)->toBe(190"),
        (r"expect\(\$seam\)->toBe\(188", "expect($seam)->toBe(189")
    ],
    'app/tests/Feature/Architecture/OwnerNavTest.php': [
        (r"expect\(count\(\$invisible\)\)->toBe\(71", "expect(count($invisible))->toBe(70"),
        (r"expect\(\$withoutLayout\)->toBe\(62", "expect($withoutLayout)->toBe(61"),
        (r"expect\(\$unbuilt\)->toBe\(44", "expect($unbuilt)->toBe(43")
    ],
    'app/tests/Feature/Architecture/SampleStateModuleTest.php': [
        (r"expect\(\$total\)->toBe\(59", "expect($total)->toBe(58"),
        (r"expect\(\$illegal\)->toBe\(50", "expect($illegal)->toBe(49")
    ]
}

for filepath, replacements in files.items():
    with open(filepath, 'r') as f:
        content = f.read()
    for old, new in replacements:
        content = re.sub(old, new, content)
    with open(filepath, 'w') as f:
        f.write(content)
