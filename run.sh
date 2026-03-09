#!/bin/bash
# GASSIT Ralph Loop - Windows Git Bash compatible
MAX=${1:-100}

echo "Starting GASSIT Ralph Loop — max $MAX iterations"
echo "Working dir: $(pwd)"

for i in $(seq 1 $MAX); do
  echo ""
  echo "======================================================="
  echo "  Iteration $i of $MAX — $(date '+%H:%M:%S')"
  echo "======================================================="

  claude --dangerously-skip-permissions --print < CLAUDE.md

  EXIT=$?

  if [ $EXIT -ne 0 ]; then
    echo "Claude exited with code $EXIT — stopping."
    exit $EXIT
  fi

  # Check progress.txt for COMPLETE signal
  if grep -q "COMPLETE" progress.txt 2>/dev/null; then
    echo ""
    echo "All stories complete!"
    exit 0
  fi

  echo ""
  echo "Iteration $i done. Sleeping 3s..."
  sleep 3
done

echo "Reached max iterations ($MAX)."
