"""The GO AI EZ voice worker — the AI receptionist's live half (AI receptionist plan, 2026-10-05).

This package holds the parts that need no vendor: the signed brain client, the price guard, the outbox and the fallback
script. The LiveKit pipeline that uses them (speech-to-text, the language model, text-to-speech) is added on the voice
server, where those vendors' keys live and nowhere else (owner ruling D-9).
"""
