import unittest

from goaiez_voice.guard import NO_PRICE_WORDS, PriceGuard, figures_in


class PriceGuardTest(unittest.TestCase):
    def test_a_sentence_with_no_money_is_spoken_as_it_is(self):
        guard = PriceGuard()
        self.assertEqual(guard.speakable("We are open until six."), "We are open until six.")
        self.assertEqual(guard.speakable("Call us on 555 0100, suite 12."), "Call us on 555 0100, suite 12.")

    def test_a_figure_the_tool_never_gave_is_replaced(self):
        guard = PriceGuard()
        self.assertEqual(guard.speakable("The callout fee is $85."), NO_PRICE_WORDS)
        self.assertEqual(guard.speakable("It is usually 120 dollars."), NO_PRICE_WORDS)
        self.assertEqual(guard.speakable("About eighty-five dollars."), NO_PRICE_WORDS)

    def test_a_figure_the_tool_gave_on_this_call_is_spoken_in_any_of_its_forms(self):
        guard = PriceGuard()
        guard.grant({"status": "price", "amount_cents": 8500, "spoken": "$85.00"})
        self.assertEqual(guard.speakable("The callout fee is $85.00."), "The callout fee is $85.00.")
        self.assertEqual(guard.speakable("That is $85."), "That is $85.")
        self.assertEqual(guard.speakable("That is 85 dollars."), "That is 85 dollars.")
        # A different figure in the same sentence is still refused.
        self.assertEqual(guard.speakable("$85, or $95 after hours."), NO_PRICE_WORDS)

    def test_a_refusal_from_the_tool_grants_nothing(self):
        guard = PriceGuard()
        guard.grant({"status": "refused", "refusal_code": "NO_FACT", "amount_cents": 8500})
        self.assertEqual(guard.speakable("It is $85."), NO_PRICE_WORDS)

    def test_figures_are_read_to_cents(self):
        self.assertEqual(figures_in("$1,250.50 or £3"), {125050, 300})
        self.assertEqual(figures_in("12 bucks"), {1200})
        self.assertEqual(figures_in("no money here, 42 times"), set())


if __name__ == "__main__":
    unittest.main()
