import re

def resolve_file(filepath, keep_both=False):
    with open(filepath, 'r') as f:
        content = f.read()

    if keep_both:
        # For OwnerNav.php
        def replacer(match):
            return match.group(1) + match.group(2)
        resolved = re.sub(r'<<<<<<< HEAD\n(.*?)\n=======\n(.*?)\n>>>>>>>.*?\n', replacer, content, flags=re.DOTALL)
    else:
        # For tests, take INCOMING
        def replacer(match):
            return match.group(2)
        resolved = re.sub(r'<<<<<<< HEAD\n(.*?)\n=======\n(.*?)\n>>>>>>>.*?\n', replacer, content, flags=re.DOTALL)

    with open(filepath, 'w') as f:
        f.write(resolved)

resolve_file('app/app/Support/Account/OwnerNav.php', keep_both=True)
resolve_file('app/tests/Feature/Architecture/HeadingSeamTest.php', keep_both=False)
resolve_file('app/tests/Feature/Architecture/OwnerNavTest.php', keep_both=False)
resolve_file('app/tests/Feature/Architecture/SampleStateModuleTest.php', keep_both=False)
