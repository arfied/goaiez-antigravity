#!/bin/bash
export FAKE_RUNNER_MODE=hang
exec "$(dirname "$0")/fake-runner.sh" "$@"
