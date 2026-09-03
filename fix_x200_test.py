import re
with open('app/tests/Modules/X-200/X200Test.php', 'r') as f:
    text = f.read()

# Add [G18-25] to the PHPDoc block listing capabilities
text = text.replace("[G18-19]", "[G18-19], [G18-25]")

with open('app/tests/Modules/X-200/X200Test.php', 'w') as f:
    f.write(text)
