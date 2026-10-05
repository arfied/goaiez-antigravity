"""The worker's outbox: requests the brain did not acknowledge are kept on disk and sent again (wave 4a).

A call keeps going when the brain is briefly unreachable — what was said, a message left, the call's end — and none of it
may be lost. Each request is stored in SQLite before it is sent and deleted only after the brain answered below 500.
The brain endpoints are idempotent (turns by call and turn number, end and message once each), so sending a request twice
is safe and losing one is not.
"""

import json
import sqlite3
import time
from typing import Any, Dict

from .brain import BrainClient, BrainUnavailable


class Outbox:
    def __init__(self, path: str) -> None:
        self.db = sqlite3.connect(path)
        self.db.execute(
            "CREATE TABLE IF NOT EXISTS outbox ("
            " id INTEGER PRIMARY KEY AUTOINCREMENT,"
            " path TEXT NOT NULL,"
            " payload TEXT NOT NULL,"
            " attempts INTEGER NOT NULL DEFAULT 0,"
            " created_at REAL NOT NULL)"
        )
        self.db.commit()

    def put(self, path: str, payload: Dict[str, Any]) -> int:
        cursor = self.db.execute(
            "INSERT INTO outbox (path, payload, created_at) VALUES (?, ?, ?)",
            (path, json.dumps(payload), time.time()),
        )
        self.db.commit()
        return int(cursor.lastrowid)

    def pending(self) -> int:
        return int(self.db.execute("SELECT COUNT(*) FROM outbox").fetchone()[0])

    def flush(self, client: BrainClient) -> int:
        """Send everything waiting, oldest first. Stops at the first failure so order is kept. Returns how many were sent."""
        sent = 0
        rows = self.db.execute("SELECT id, path, payload FROM outbox ORDER BY id").fetchall()
        for row_id, path, payload in rows:
            try:
                status, _ = client.post(path, json.loads(payload))
            except BrainUnavailable:
                self._attempted(row_id)
                break
            if status >= 500:
                self._attempted(row_id)
                break
            # Anything below 500 is an answer: a 4xx will not get better by being sent again.
            self.db.execute("DELETE FROM outbox WHERE id = ?", (row_id,))
            self.db.commit()
            sent += 1
        return sent

    def _attempted(self, row_id: int) -> None:
        self.db.execute("UPDATE outbox SET attempts = attempts + 1 WHERE id = ?", (row_id,))
        self.db.commit()
