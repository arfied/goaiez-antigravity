import os
import tempfile
import unittest

from goaiez_voice.brain import BrainClient
from goaiez_voice.outbox import Outbox


class ScriptedTransport:
    """Answers each request with the next status in the script; raises OSError for 'down'."""

    def __init__(self, script):
        self.script = list(script)
        self.paths = []

    def __call__(self, url, body, headers, timeout):
        self.paths.append(url)
        status = self.script.pop(0)
        if status == "down":
            raise OSError("connection refused")
        return status, b"{}"


class OutboxTest(unittest.TestCase):
    def setUp(self):
        handle, self.path = tempfile.mkstemp(suffix=".sqlite")
        os.close(handle)

    def tearDown(self):
        os.unlink(self.path)

    def client(self, transport):
        return BrainClient("https://brain.test", "s", transport=transport)

    def test_nothing_is_lost_while_the_brain_is_down_and_everything_is_sent_in_order_once_it_is_back(self):
        outbox = Outbox(self.path)
        outbox.put("/api/voice/v1/calls/t/turns", {"turns": [{"turn": 1, "caller": "a", "agent": "b"}]})
        outbox.put("/api/voice/v1/calls/t/end", {})

        down = ScriptedTransport(["down"])
        self.assertEqual(outbox.flush(self.client(down)), 0)
        self.assertEqual(outbox.pending(), 2)

        # The queue survives a restart of the worker.
        reopened = Outbox(self.path)
        up = ScriptedTransport([200, 200])
        self.assertEqual(reopened.flush(self.client(up)), 2)
        self.assertEqual(reopened.pending(), 0)
        self.assertEqual(up.paths, ["https://brain.test/api/voice/v1/calls/t/turns", "https://brain.test/api/voice/v1/calls/t/end"])

    def test_a_server_error_keeps_the_request_and_stops_so_order_is_kept(self):
        outbox = Outbox(self.path)
        outbox.put("/first", {})
        outbox.put("/second", {})

        transport = ScriptedTransport([503])
        self.assertEqual(outbox.flush(self.client(transport)), 0)
        self.assertEqual(outbox.pending(), 2)
        self.assertEqual(len(transport.paths), 1)

    def test_a_client_error_is_an_answer_and_is_not_sent_again(self):
        outbox = Outbox(self.path)
        outbox.put("/gone", {})

        self.assertEqual(outbox.flush(self.client(ScriptedTransport([404]))), 1)
        self.assertEqual(outbox.pending(), 0)


if __name__ == "__main__":
    unittest.main()
