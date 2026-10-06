import json
import unittest

from goaiez_voice.brain import SIGNATURE_HEADER, TIMESTAMP_HEADER, BrainClient, BrainUnavailable, encode, sign


class FakeTransport:
    def __init__(self, status=200, body=b'{"status":"answer","call_token":"t0k"}', fail=False):
        self.status = status
        self.body = body
        self.fail = fail
        self.calls = []

    def __call__(self, url, body, headers, timeout):
        self.calls.append((url, body, headers))
        if self.fail:
            raise OSError("connection refused")
        return self.status, self.body


class SigningTest(unittest.TestCase):
    def test_the_signature_matches_the_php_side_byte_for_byte(self):
        # The vector was computed with PHP's hash_hmac('sha256', "1759680000.{...}", 'voice-secret-9101') — the formula
        # App\Http\Middleware\VerifyVoiceWorker checks — and with Python's hmac; both printed this hex.
        self.assertEqual(
            sign("voice-secret-9101", 1759680000, '{"dialled_e164":"+15555550931"}'),
            "f73afb25316016852fa03eb3b885c4cf7843a19fb81e7e4188e60b25bc57f279",
        )

    def test_the_signed_bytes_are_the_sent_bytes(self):
        transport = FakeTransport()
        client = BrainClient("https://brain.test/", "voice-secret-9101", transport=transport, clock=lambda: 1759680000)

        client.start_call("+15555550931", "+14155550932", "SCL_1")

        url, body, headers = transport.calls[0]
        self.assertEqual(url, "https://brain.test/api/voice/v1/calls")
        self.assertEqual(headers[TIMESTAMP_HEADER], "1759680000")
        self.assertEqual(headers[SIGNATURE_HEADER], sign("voice-secret-9101", 1759680000, body.decode("utf-8")))
        self.assertEqual(json.loads(body), {"dialled_e164": "+15555550931", "from_e164": "+14155550932", "transport_call_id": "SCL_1"})

    def test_later_requests_carry_the_call_token_in_the_path_and_never_a_business(self):
        transport = FakeTransport(body=b'{"status":"recorded","written":1}')
        client = BrainClient("https://brain.test", "s", transport=transport)

        client.record_turns("tok-1", [{"turn": 1, "caller": "hi", "agent": "hello"}])
        client.price("tok-1", "How much is your callout fee?")
        client.leave_message("tok-1", "Ring me", name="Dana", callback="+14155550999")
        client.end_call("tok-1")

        paths = [call[0] for call in transport.calls]
        self.assertEqual(paths, [
            "https://brain.test/api/voice/v1/calls/tok-1/turns",
            "https://brain.test/api/voice/v1/calls/tok-1/tools/price",
            "https://brain.test/api/voice/v1/calls/tok-1/message",
            "https://brain.test/api/voice/v1/calls/tok-1/end",
        ])
        for _, body, _ in transport.calls:
            self.assertNotIn("business_id", body.decode("utf-8"))

    def test_an_unreachable_brain_or_a_server_error_is_brain_unavailable(self):
        with self.assertRaises(BrainUnavailable):
            BrainClient("https://brain.test", "s", transport=FakeTransport(fail=True)).start_call("+1", "+2", "SCL_2")
        with self.assertRaises(BrainUnavailable):
            BrainClient("https://brain.test", "s", transport=FakeTransport(status=503)).start_call("+1", "+2", "SCL_3")
        with self.assertRaises(BrainUnavailable):
            BrainClient("https://brain.test", "s", transport=FakeTransport(body=b"<html>")).start_call("+1", "+2", "SCL_4")

    def test_the_heartbeat_is_signed_and_names_no_business(self):
        transport = FakeTransport(body=b'{"status":"ok"}')
        client = BrainClient("https://brain.test", "s", transport=transport, clock=lambda: 1759680000)

        self.assertEqual(client.heartbeat()["status"], "ok")

        url, body, headers = transport.calls[0]
        self.assertEqual(url, "https://brain.test/api/voice/v1/heartbeat")
        self.assertEqual(body, b"{}")
        self.assertEqual(headers[SIGNATURE_HEADER], sign("s", 1759680000, "{}"))

    def test_a_worker_with_no_secret_refuses_to_start(self):
        with self.assertRaises(ValueError):
            BrainClient("https://brain.test", "")

    def test_encoding_is_compact(self):
        self.assertEqual(encode({"a": 1, "b": "x"}), '{"a":1,"b":"x"}')


if __name__ == "__main__":
    unittest.main()
