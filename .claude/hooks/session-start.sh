#!/bin/bash
#
# SessionStart hook — prepares a Claude Code on the web container for this repo.
#
# Web sessions only. A local machine already has its own Postgres, its own vendor/ and its own
# node_modules, and re-running an installer there would be slower and might disagree with what the
# developer has set up on purpose.
#
# Synchronous, so nothing in the session can race a half-finished install. The cost is a slower
# session start on a cold container; the work is cached afterwards. Add
# `echo '{"async": true, "asyncTimeout": 600000}'` as the first line to trade that back.

set -uo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

exec bash "$(dirname "${BASH_SOURCE[0]}")/install-deps.sh"
