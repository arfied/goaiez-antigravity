"""The price guard: no amount of money is spoken on a call unless the price tool gave it on this call (wave 4a).

The receptionist is never handed a price list. A price reaches a call only through the price tool
(`POST /api/voice/v1/calls/{token}/tools/price`), which answers from the business's confirmed prices. The language model can
still produce a figure on its own, so every sentence is checked BEFORE it is spoken: one that names an amount the tool did
not give on this call is cancelled and replaced with `NO_PRICE_WORDS`.

Read figures to cents so "$85", "$85.00" and "85 dollars" are the same figure. Words-only amounts ("eighty-five dollars")
are refused outright: a confirmed price is always handed over as digits, so a figure in words is one the model made up.
"""

import re
from decimal import Decimal, InvalidOperation
from typing import Optional, Set

NO_PRICE_WORDS = "I don't have a confirmed price for that. The business will confirm it and get back to you."

_SYMBOL_FIRST = re.compile(r"[$£€]\s?(\d{1,3}(?:,\d{3})+|\d+)(?:\.(\d{1,2}))?")
_WORD_AFTER = re.compile(
    r"\b(\d{1,3}(?:,\d{3})+|\d+)(?:\.(\d{1,2}))?\s*(?:dollars?|bucks|pounds?|euros?|usd|gbp|eur)\b",
    re.IGNORECASE,
)
_WORD_NUMBER_MONEY = re.compile(
    r"\b(?:one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|"
    r"eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand)\b[\w\s-]*?"
    r"\b(?:dollars?|bucks|pounds?|euros?)\b",
    re.IGNORECASE,
)


def _cents(whole: str, fraction: Optional[str]) -> Optional[int]:
    try:
        value = Decimal(whole.replace(",", "") + "." + (fraction or "0"))
    except InvalidOperation:
        return None
    return int((value * 100).to_integral_value())


def figures_in(sentence: str) -> Set[int]:
    """Every amount of money a sentence names, in cents."""
    found: Set[int] = set()
    for pattern in (_SYMBOL_FIRST, _WORD_AFTER):
        for match in pattern.finditer(sentence):
            cents = _cents(match.group(1), match.group(2))
            if cents is not None:
                found.add(cents)
    return found


class PriceGuard:
    """One per call: remembers what the price tool gave, and judges each sentence before it is spoken."""

    def __init__(self) -> None:
        self.granted: Set[int] = set()

    def grant(self, tool_answer: dict) -> None:
        """Record a price-tool answer. Only `status: price` grants anything."""
        if tool_answer.get("status") == "price" and isinstance(tool_answer.get("amount_cents"), int):
            self.granted.add(tool_answer["amount_cents"])

    def allows(self, sentence: str) -> bool:
        if _WORD_NUMBER_MONEY.search(sentence):
            return False
        return figures_in(sentence) <= self.granted

    def speakable(self, sentence: str) -> str:
        """The sentence to actually say: itself, or the no-price words when it names an amount nobody confirmed."""
        return sentence if self.allows(sentence) else NO_PRICE_WORDS
