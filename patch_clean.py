import sys
for file_path in sys.argv[1:]:
    with open(file_path, 'r') as f:
        lines = f.readlines()
    
    out = []
    i = 0
    while i < len(lines):
        if lines[i].startswith('<<<<<<<'):
            i += 1
            # collect ours
            while not lines[i].startswith('======='):
                out.append(lines[i])
                i += 1
            i += 1 # skip =======
            # skip theirs
            while not lines[i].startswith('>>>>>>>'):
                i += 1
            i += 1 # skip >>>>>>>
        else:
            out.append(lines[i])
            i += 1
    
    with open(file_path, 'w') as f:
        f.writelines(out)
