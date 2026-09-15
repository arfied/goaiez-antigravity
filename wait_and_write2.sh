#!/bin/bash
while true; do
  if grep -q "tests 2648" .agents/supervisor/.gate-sx196.txt; then
    break
  fi
  sleep 5
done
sleep 2

GATE_LAST_LINE=$(tail -n 1 .agents/supervisor/.gate-sx196.txt)
GATE_LS=$(ls -la --time-style=full-iso .agents/supervisor/.gate-sx196.txt)

sed -i "s|^… another suite holds .*|$GATE_LAST_LINE|g" .agents/supervisor/REPORT.md
sed -i "s|^\-rw-r--r-- 1 goaiez goaiez .* .agents/supervisor/.gate-sx196.txt|$GATE_LS|g" .agents/supervisor/REPORT.md
