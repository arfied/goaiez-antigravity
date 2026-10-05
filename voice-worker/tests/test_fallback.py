import os
import re
import unittest

from goaiez_voice.fallback import ANNOUNCEMENT, TAKE_A_MESSAGE, answers, script

GREETING_PHP = os.path.join(os.path.dirname(__file__), "..", "..", "app", "app", "Services", "Voice", "VoiceGreeting.php")


class FallbackTest(unittest.TestCase):
    def test_the_announcement_comes_first_and_the_caller_is_asked_for_a_message(self):
        self.assertEqual(script(None), [ANNOUNCEMENT, TAKE_A_MESSAGE])
        self.assertEqual(script({"status": "declined", "reason": "unknown_number"}), [ANNOUNCEMENT, TAKE_A_MESSAGE])

    def test_the_brains_own_announcement_wins_when_it_sent_one(self):
        self.assertEqual(script({"status": "disabled", "announcement": "Calls are recorded."})[0], "Calls are recorded.")

    def test_only_an_answer_with_a_token_lets_the_receptionist_speak(self):
        self.assertTrue(answers({"status": "answer", "call_token": "t"}))
        self.assertFalse(answers({"status": "answer"}))
        self.assertFalse(answers({"status": "answer", "call_token": ""}))
        self.assertFalse(answers({"status": "declined", "reason": "owner_first_not_built"}))
        self.assertFalse(answers({"status": "disabled"}))
        self.assertFalse(answers(None))

    @unittest.skipUnless(os.path.exists(GREETING_PHP), "runs only inside the repository, beside the Laravel app")
    def test_the_announcement_is_the_laravel_sides_sentence_exactly(self):
        with open(GREETING_PHP, encoding="utf-8") as handle:
            source = handle.read()
        match = re.search(r"public const string ANNOUNCEMENT = '([^']*)';", source)
        self.assertIsNotNone(match)
        self.assertEqual(match.group(1), ANNOUNCEMENT)


if __name__ == "__main__":
    unittest.main()
