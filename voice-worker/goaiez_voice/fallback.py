"""What a caller hears when the AI receptionist does not answer (wave 4a).

Used when call start says `disabled` or `declined`, or when the brain cannot be reached at all. The recording announcement
is always first — the same sentence every recorded call plays (`App\\Services\\Voice\\VoiceGreeting::ANNOUNCEMENT`) — and
then the caller is asked to leave their name and number, which the outbox delivers to the brain once it is reachable.
"""

from typing import List, Optional

# Must match App\Services\Voice\VoiceGreeting::ANNOUNCEMENT exactly; tests/test_fallback.py holds them together.
ANNOUNCEMENT = "This call is recorded."

TAKE_A_MESSAGE = (
    "Sorry, nobody can take your call right now. "
    "Please leave your name, your number and a short message after the tone, and the business will get back to you."
)


def script(call_start_answer: Optional[dict]) -> List[str]:
    """The sentences to play, in order, when the receptionist will not answer this call."""
    announcement = ANNOUNCEMENT
    if isinstance(call_start_answer, dict) and isinstance(call_start_answer.get("announcement"), str):
        announcement = call_start_answer["announcement"]
    return [announcement, TAKE_A_MESSAGE]


def answers(call_start_answer: Optional[dict]) -> bool:
    """Whether call start said the receptionist may answer: only an explicit `status: answer` with a token does."""
    return (
        isinstance(call_start_answer, dict)
        and call_start_answer.get("status") == "answer"
        and isinstance(call_start_answer.get("call_token"), str)
        and call_start_answer["call_token"] != ""
    )
