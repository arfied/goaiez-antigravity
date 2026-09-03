import re
with open('app/tests/Modules/X-205/X205Test.php', 'r') as f:
    text = f.read()

# Add missing IDs to the PHPDoc block listing capabilities
text = text.replace("[G13-20]", "[G7-04], [G7-11], [G7-23], [G7-41], [G7-45], [G10-36], [G13-20]")

with open('app/tests/Modules/X-205/X205Test.php', 'w') as f:
    f.write(text)
