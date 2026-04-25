#!/usr/bin/env bash
#
# Install the project's git hooks into .git/hooks/.
# Re-run any time after pulling new hooks.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
src="$repo_root/hooks"
dst="$repo_root/.git/hooks"

if [[ ! -d "$dst" ]]; then
    echo "ERROR: $dst does not exist. Is this a git repo?" >&2
    exit 1
fi

for hook in pre-commit; do
    cp "$src/$hook" "$dst/$hook"
    chmod +x "$dst/$hook"
    echo "Installed: $dst/$hook"
done

echo "Done."
