import re

with open('app/tests/Feature/Architecture/OwnerNavTest.php', 'r') as f:
    content = f.read()

content = re.sub(r'<<<<<<< HEAD\n.*?\n=======\n(.*?)\n>>>>>>> origin/track/pricebook\n', r'\1\n', content, flags=re.DOTALL)

with open('app/tests/Feature/Architecture/OwnerNavTest.php', 'w') as f:
    f.write(content)
