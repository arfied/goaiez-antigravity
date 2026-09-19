import sys

def resolve_incoming(filepath):
    with open(filepath, 'r') as f:
        lines = f.readlines()
    
    new_lines = []
    in_ours = False
    in_theirs = False
    for line in lines:
        if line.startswith('<<<<<<< HEAD'):
            in_ours = True
        elif line.startswith('======='):
            in_ours = False
            in_theirs = True
        elif line.startswith('>>>>>>> origin/track/money'):
            in_theirs = False
        else:
            if in_ours:
                pass
            else:
                new_lines.append(line)
                
    with open(filepath, 'w') as f:
        f.writelines(new_lines)

resolve_incoming('app/tests/Feature/Architecture/HeadingSeamTest.php')
resolve_incoming('app/tests/Feature/Architecture/OwnerNavTest.php')
