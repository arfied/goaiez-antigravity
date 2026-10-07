#!/bin/bash
export FAKE_RUNNER_MODE=fail
exec "$(dirname "$0")/fake-runner.sh" "$@"
