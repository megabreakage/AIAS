#!/usr/bin/env bash
# Rewrite the last N commit messages (default 50) using GitHub Copilot CLI.
#
# For each commit, the script:
#   1. Collects changed files, diff stat, and a truncated diff
#   2. Asks `copilot -p` to generate a Conventional Commit message
#   3. Stores the new message in a temp file
# Then it runs `git rebase -i HEAD~N` with a non-interactive editor that
# rewords each commit using the pre-generated messages.
#
# WARNING: Rewrites git history. Only use on branches that are unpushed
# or safe to force-push (--force-with-lease).
#
# Usage:
#   ./rewrite-commits.sh                # rewrite last 50 commits
#   ./rewrite-commits.sh 20             # rewrite last 20 commits
#   ./rewrite-commits.sh 50 --yes       # skip confirmation prompt
#   ./rewrite-commits.sh 50 --dry-run   # generate messages only, no rebase

set -euo pipefail

N="${1:-50}"
shift || true

ASSUME_YES=false
DRY_RUN=false
for arg in "$@"; do
    case "$arg" in
        --yes|-y) ASSUME_YES=true ;;
        --dry-run) DRY_RUN=true ;;
    esac
done

# --- Pre-flight checks ---------------------------------------------------
command -v copilot >/dev/null || { echo "Error: copilot CLI not found. Install GitHub Copilot CLI."; exit 1; }
command -v git >/dev/null || { echo "Error: git not found"; exit 1; }

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Error: not inside a git repository"; exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Error: working tree not clean. Commit or stash changes first."; exit 1
fi

BRANCH=$(git rev-parse --abbrev-ref HEAD)
TOTAL=$(git rev-list --count HEAD)
if (( N > TOTAL )); then
    echo "Error: requested $N commits, branch only has $TOTAL"; exit 1
fi

echo "Branch: $BRANCH"
echo "Rewriting last $N commit messages using Copilot CLI..."
echo ""

# --- Workspace -----------------------------------------------------------
WORK_DIR=$(mktemp -d -t rewrite-commits-XXXXXX)
MSG_DIR="$WORK_DIR/messages"
mkdir -p "$MSG_DIR"
echo "Workspace: $WORK_DIR"

cleanup() {
    if [[ "$DRY_RUN" == "true" ]]; then
        echo "Dry-run: keeping messages at $MSG_DIR"
    else
        rm -rf "$WORK_DIR"
    fi
}
trap cleanup EXIT

# Get commits oldest-first (rebase processes them in that order)
# Use while-read instead of mapfile for bash 3.2 compatibility (macOS default)
COMMITS=()
while IFS= read -r sha; do
    COMMITS+=("$sha")
done < <(git --no-pager log --format="%H" -n "$N" --reverse)

# --- Generate messages ---------------------------------------------------
DIFF_LIMIT=4000   # max chars of diff body sent to Copilot
i=0
for sha in "${COMMITS[@]}"; do
    i=$((i + 1))
    short="${sha:0:7}"
    old_subject=$(git --no-pager log --format="%s" -1 "$sha")
    msg_file="$MSG_DIR/$i.msg"

    printf '[%2d/%d] %s  old: %s\n' "$i" "$N" "$short" "$old_subject"

    files=$(git --no-pager diff-tree --no-commit-id --name-only -r "$sha")
    stat=$(git --no-pager show --stat --format="" "$sha")
    body=$(git --no-pager show --format="" --no-color "$sha" | head -c "$DIFF_LIMIT")

    prompt=$(cat <<PROMPT
You are generating a single git commit message in Conventional Commits format.
Output ONLY the commit message (subject line, and optional body separated by a blank line).
Do not include code fences, quotes, explanations, or any preamble.

Subject rules:
- <= 72 chars
- Lowercase imperative type prefix: feat|fix|refactor|chore|docs|test|style|perf|build|ci
- Optional scope in parens
- No trailing period

Body rules (only if changes are non-trivial):
- Wrap at 80 chars
- Bullet list of concrete changes
- Skip body for trivial/single-line changes

Changed files:
$files

Diff stat:
$stat

Diff (truncated):
$body
PROMPT
)

    # Call Copilot non-interactively. --allow-all-tools required for -p mode.
    # Use low reasoning effort for speed.
    if ! out=$(printf '%s' "$prompt" | copilot \
            -p /dev/stdin \
            --allow-all-tools \
            --no-banner \
            --effort low 2>"$WORK_DIR/err.log"); then
        echo "  ! copilot failed for $short (see $WORK_DIR/err.log), falling back"
        out="chore: update $(echo "$files" | head -1)"
    fi

    # Strip ANSI escape codes and leading/trailing blank lines
    cleaned=$(printf '%s' "$out" \
        | sed -E 's/\x1b\[[0-9;]*[a-zA-Z]//g' \
        | awk 'NF{found=1} found' \
        | sed -e :a -e '/^$/{$d;N;ba' -e '}')

    if [[ -z "$cleaned" ]]; then
        cleaned="chore: update $(echo "$files" | head -1)"
    fi

    printf '%s\n' "$cleaned" > "$msg_file"
    subject=$(head -1 "$msg_file")
    printf '         new: %s\n\n' "$subject"
done

# --- Preview -------------------------------------------------------------
echo "─────────────────────────────────────────────"
echo "Generated messages preview:"
for i in $(seq 1 "$N"); do
    subject=$(head -1 "$MSG_DIR/$i.msg")
    printf '  %2d. %s\n' "$i" "$subject"
done
echo "─────────────────────────────────────────────"
echo ""

if [[ "$DRY_RUN" == "true" ]]; then
    echo "Dry-run complete. Messages saved at: $MSG_DIR"
    exit 0
fi

if [[ "$ASSUME_YES" != "true" ]]; then
    read -rp "Proceed with rewrite of $N commits on '$BRANCH'? (y/N): " confirm
    if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
        echo "Aborted."
        exit 0
    fi
fi

# --- Rebase --------------------------------------------------------------
SEQ_EDITOR="$WORK_DIR/seq-editor.sh"
cat > "$SEQ_EDITOR" <<'SEQ'
#!/usr/bin/env bash
sed -i.bak -E 's/^pick /reword /' "$1"
SEQ
chmod +x "$SEQ_EDITOR"

COUNTER_FILE="$WORK_DIR/counter"
echo "1" > "$COUNTER_FILE"

MSG_EDITOR="$WORK_DIR/msg-editor.sh"
cat > "$MSG_EDITOR" <<EDITOR
#!/usr/bin/env bash
COMMIT_FILE="\$1"
COUNTER_FILE="$COUNTER_FILE"
MSG_DIR="$MSG_DIR"

N=\$(cat "\$COUNTER_FILE")
MSG_FILE="\$MSG_DIR/\$N.msg"

if [[ -f "\$MSG_FILE" ]]; then
    cat "\$MSG_FILE" > "\$COMMIT_FILE"
fi

echo \$(( N + 1 )) > "\$COUNTER_FILE"
EDITOR
chmod +x "$MSG_EDITOR"

echo ""
echo "Running git rebase -i HEAD~$N ..."
GIT_SEQUENCE_EDITOR="$SEQ_EDITOR" \
GIT_EDITOR="$MSG_EDITOR" \
git rebase -i "HEAD~$N"

echo ""
echo "Done. New commit log:"
echo "─────────────────────────────────────────────"
git --no-pager log --oneline -n "$N"
echo ""
echo "To publish: git push --force-with-lease origin $BRANCH"
