import re
with open('tests/Modules/X-186/X186Test.php', 'r') as f:
    text = f.read()

text = text.replace(
    "[G2-14], [G5-12], [G5-14], [G10-25], [G11-02], [G11-24], [G11-28], [G11-31], [G12-18], [G12-28], [G17-20], [G19-04]",
    "[G2-14], [G3-53], [G5-12], [G5-14], [G10-25], [G11-02], [G11-24], [G11-28], [G11-31], [G12-18], [G12-28], [G17-20], [G19-04]"
)

with open('tests/Modules/X-186/X186Test.php', 'w') as f:
    f.write(text)
