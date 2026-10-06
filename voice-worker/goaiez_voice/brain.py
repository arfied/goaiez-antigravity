"""The voice worker's client for the voice brain API (AI receptionist plan, wave 4a).

Every request is signed exactly as the Laravel side checks it (`App\\Http\\Middleware\\VerifyVoiceWorker`):

    X-Voice-Timestamp: <unix seconds>
    X-Voice-Signature: hex HMAC-SHA256 of "<timestamp>.<raw body>" with the voice_worker_secret

The tenant and the call are never sent in a body. Call start answers with a call token, and every later request carries
that token in its path; Laravel derives the business and the call from it and from nothing else.

Standard library only, so the signing rule can be tested on any machine with Python 3.9+.
"""

import hashlib
import hmac
import json
import time
import urllib.error
import urllib.request
from typing import Any, Callable, Dict, Optional, Tuple

TIMESTAMP_HEADER = "X-Voice-Timestamp"
SIGNATURE_HEADER = "X-Voice-Signature"


def sign(secret: str, timestamp: int, body: str) -> str:
    """The signature for one request: hex HMAC-SHA256 of "<timestamp>.<body>"."""
    message = "{}.{}".format(timestamp, body).encode("utf-8")
    return hmac.new(secret.encode("utf-8"), message, hashlib.sha256).hexdigest()


def encode(payload: Dict[str, Any]) -> str:
    """The exact bytes that are signed and sent. Compact, so the signed string is the sent string."""
    return json.dumps(payload, separators=(",", ":"), ensure_ascii=False)


Transport = Callable[[str, bytes, Dict[str, str], float], Tuple[int, bytes]]


def urllib_transport(url: str, body: bytes, headers: Dict[str, str], timeout: float) -> Tuple[int, bytes]:
    request = urllib.request.Request(url, data=body, headers=headers, method="POST")
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.read()


class BrainUnavailable(Exception):
    """The brain could not be reached, or answered with something that is not JSON. The caller falls back."""


class BrainClient:
    def __init__(
        self,
        base_url: str,
        secret: str,
        timeout: float = 3.0,
        transport: Transport = urllib_transport,
        clock: Callable[[], float] = time.time,
    ) -> None:
        if not secret:
            # The Laravel side refuses every request when its key is unset; a worker with no key would only ever fail.
            raise ValueError("voice_worker_secret is required")
        self.base_url = base_url.rstrip("/")
        self.secret = secret
        self.timeout = timeout
        self.transport = transport
        self.clock = clock

    def post(self, path: str, payload: Dict[str, Any]) -> Tuple[int, Dict[str, Any]]:
        body = encode(payload)
        timestamp = int(self.clock())
        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json",
            TIMESTAMP_HEADER: str(timestamp),
            SIGNATURE_HEADER: sign(self.secret, timestamp, body),
        }
        try:
            status, raw = self.transport(self.base_url + path, body.encode("utf-8"), headers, self.timeout)
        except OSError as error:
            raise BrainUnavailable(str(error)) from error
        try:
            data = json.loads(raw.decode("utf-8") or "{}")
        except ValueError as error:
            raise BrainUnavailable("the brain answered with something that is not JSON") from error
        if not isinstance(data, dict):
            raise BrainUnavailable("the brain answered with JSON that is not an object")
        return status, data

    # The calls the worker makes, by the routes Laravel serves.

    def heartbeat(self) -> Dict[str, Any]:
        """Say the worker is alive. Sent every 30 seconds; the brain pages the operator when beats stop while the
        receptionist is switched on (voice.worker.heartbeat_stale_minutes)."""
        status, data = self.post("/api/voice/v1/heartbeat", {})
        if status >= 500:
            raise BrainUnavailable("heartbeat answered {}".format(status))
        data["_status"] = status
        return data


    def start_call(self, dialled_e164: str, from_e164: str, transport_call_id: str) -> Dict[str, Any]:
        status, data = self.post("/api/voice/v1/calls", {
            "dialled_e164": dialled_e164,
            "from_e164": from_e164,
            "transport_call_id": transport_call_id,
        })
        if status >= 500:
            raise BrainUnavailable("call start answered {}".format(status))
        return data

    def record_turns(self, call_token: str, turns: list) -> Dict[str, Any]:
        return self._token_post(call_token, "/turns", {"turns": turns})

    def price(self, call_token: str, question: str) -> Dict[str, Any]:
        return self._token_post(call_token, "/tools/price", {"question": question})

    def leave_message(self, call_token: str, message: str, name: Optional[str] = None, callback: Optional[str] = None) -> Dict[str, Any]:
        payload: Dict[str, Any] = {"message": message}
        if name:
            payload["name"] = name
        if callback:
            payload["callback"] = callback
        return self._token_post(call_token, "/message", payload)

    def end_call(self, call_token: str) -> Dict[str, Any]:
        return self._token_post(call_token, "/end", {})

    def _token_post(self, call_token: str, suffix: str, payload: Dict[str, Any]) -> Dict[str, Any]:
        status, data = self.post("/api/voice/v1/calls/{}{}".format(call_token, suffix), payload)
        if status >= 500:
            raise BrainUnavailable("{} answered {}".format(suffix, status))
        data["_status"] = status
        return data
