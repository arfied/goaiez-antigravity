import re
import sys

def resolve_ownernav():
    with open("app/app/Support/Account/OwnerNav.php", "r") as f:
        content = f.read()
    
    # We want to keep both HEAD and incoming, HEAD first.
    # <<<<<<< HEAD\n(ours)\n=======\n(theirs)\n>>>>>>> ...
    def repl(m):
        return m.group(1) + m.group(2)
    
    resolved = re.sub(r'<<<<<<< HEAD\n(.*?)\n=======\n(.*?)\n>>>>>>> origin/track/pricebook\n', repl, content, flags=re.DOTALL)
    
    with open("app/app/Support/Account/OwnerNav.php", "w") as f:
        f.write(resolved)
        
def resolve_incoming(filepath):
    with open(filepath, "r") as f:
        content = f.read()
    
    def repl(m):
        return m.group(2)
        
    resolved = re.sub(r'<<<<<<< HEAD\n(.*?)\n=======\n(.*?)\n>>>>>>> origin/track/pricebook\n', repl, content, flags=re.DOTALL)
    
    with open(filepath, "w") as f:
        f.write(resolved)

resolve_ownernav()
resolve_incoming("app/tests/Feature/Architecture/HeadingSeamTest.php")
resolve_incoming("app/tests/Feature/Architecture/OwnerNavTest.php")
resolve_incoming("app/tests/Feature/Architecture/SampleStateModuleTest.php")
