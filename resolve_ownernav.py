import re

with open("app/app/Support/Account/OwnerNav.php", "r") as f:
    content = f.read()

resolved = re.sub(
    r"<<<<<<< HEAD\n(.*?)\n=======\n(.*?)\n>>>>>>> origin/track/pricebook\n",
    r"\1\n\2\n",
    content,
    flags=re.DOTALL
)

with open("app/app/Support/Account/OwnerNav.php", "w") as f:
    f.write(resolved)
