import sys

def resolve(filepath):
    with open(filepath, 'r') as f:
        lines = f.readlines()
    
    out = []
    state = 'normal'
    for line in lines:
        if line.startswith('<<<<<<< HEAD'):
            state = 'head'
        elif line.startswith('======='):
            state = 'theirs'
        elif line.startswith('>>>>>>>'):
            state = 'normal'
        else:
            if state == 'normal' or state == 'head':
                out.append(line)
                
    with open(filepath, 'w') as f:
        f.writelines(out)

resolve('app/tests/Feature/Architecture/OwnerNavTest.php')
resolve('app/tests/Feature/Architecture/HeadingSeamTest.php')
resolve('app/tests/Feature/Architecture/SchemeTokenTest.php')
