#!/bin/bash
export FAKE_PUBLISHER_MODE=fail; exec "$(dirname "$0")/fake-publisher.sh" "$@"
