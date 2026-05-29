#!/usr/bin/env bash
# Rewrite the last 5 commit messages with meaningful conventional commit messages.
# Uses git filter-branch to rewrite history in-place.
#
# WARNING: This rewrites git history. Only use on unpushed or force-pushable branches.
# Usage: ./rewrite-commits.sh

set -euo pipefail

BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo "Branch: $BRANCH"
echo "Rewriting last 5 commit messages..."
echo ""

# Map: old SHA -> new message
# Commit 1 (oldest): 31250d5 - Auth tenancy middleware, postman collection cleanup
# Commit 2: 26d0d72 - Agent feedback timestamp update
# Commit 3: 57f99ba - Postman environments migration JSON to YAML
# Commit 4: 5d46c31 - Hide id field from model serialization
# Commit 5 (newest): e9cf60c - Remove android kotlin skill file

declare -A MESSAGES
MESSAGES["31250d55c0d4157c255c044acc6d249930780527"]="feat: add auth-based tenancy initialization middleware

- Add InitializeTenancyByAuthUser middleware for token-based tenant resolution
- Update AuthController to include tenant data in login response
- Refactor TenancyServiceProvider event handling
- Add tenant_id to users migration and UserResource
- Update Postman collection auth endpoints and tenant request definitions
- Fix PreambleController import"

MESSAGES["26d0d72501d4b89a5b207c8b1c920e1693eb2cc3"]="chore: update agent feedback timestamp"

MESSAGES["57f99bae475e321ff088cf87241cd572471caddd"]="refactor: migrate Postman environments from JSON to YAML

Replace JSON environment files with YAML equivalents for all environments
(Development, Local, Production, Staging)."

MESSAGES["5d46c31f5b49862afa8dd83f9ccec805b182521e"]="fix: hide internal id field from API serialization

Add 'id' to \$hidden in BaseModel and User model to prevent
exposing auto-increment IDs in API responses."

MESSAGES["e9cf60cacbacc92d329bba12caaf507c54589d8f"]="chore: remove unused android-kotlin-development skill"

# Show planned changes
echo "Planned rewrites:"
echo "─────────────────────────────────────────────"
for hash in 31250d55c0d4157c255c044acc6d249930780527 \
            26d0d72501d4b89a5b207c8b1c920e1693eb2cc3 \
            57f99bae475e321ff088cf87241cd572471caddd \
            5d46c31f5b49862afa8dd83f9ccec805b182521e \
            e9cf60cacbacc92d329bba12caaf507c54589d8f; do
    short="${hash:0:7}"
    old_msg=$(git --no-pager log --format="%s" -1 "$hash" 2>/dev/null || echo "(not found)")
    new_subject=$(echo "${MESSAGES[$hash]}" | head -1)
    echo "  $short: $old_msg"
    echo "       → $new_subject"
    echo ""
done

read -rp "Proceed with rewrite? (y/N): " confirm
if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
    echo "Aborted."
    exit 0
fi

# Use git-filter-branch with msg-filter to rewrite
FILTER_SCRIPT=""
for hash in "${!MESSAGES[@]}"; do
    msg="${MESSAGES[$hash]}"
    # Escape for sed
    escaped_msg=$(printf '%s' "$msg" | sed 's/[&/\]/\\&/g')
    FILTER_SCRIPT+="if [ \"\$GIT_COMMIT\" = \"$hash\" ]; then printf '%s' '$( printf '%s' "$msg" | sed "s/'/'\\\\''/g" )'; else "
done
FILTER_SCRIPT+="cat; "
for _ in "${!MESSAGES[@]}"; do
    FILTER_SCRIPT+="fi; "
done

# Simpler approach: use environment filter with COMMIT_MSG file
export REWRITE_MAP_FILE
REWRITE_MAP_FILE=$(mktemp)

for hash in "${!MESSAGES[@]}"; do
    printf '%s\n' "$hash" >> "$REWRITE_MAP_FILE"
    printf '%s\n' "${MESSAGES[$hash]}" >> "$REWRITE_MAP_FILE"
    printf '%s\n' "---END---" >> "$REWRITE_MAP_FILE"
done

# Use interactive rebase with exec to reword each commit
# Simplest reliable approach: create a sequence editor script

SEQUENCE_EDITOR_SCRIPT=$(mktemp)
cat > "$SEQUENCE_EDITOR_SCRIPT" << 'SEDSCRIPT'
#!/usr/bin/env bash
# Replace 'pick' with 'reword' for all lines
sed -i.bak 's/^pick /reword /g' "$1"
SEDSCRIPT
chmod +x "$SEQUENCE_EDITOR_SCRIPT"

# Create commit message editor script
COMMIT_MSG_EDITOR=$(mktemp)
cat > "$COMMIT_MSG_EDITOR" << EDITORSCRIPT
#!/usr/bin/env bash
# This editor replaces commit messages based on the original commit
COMMIT_FILE="\$1"
CURRENT_MSG=\$(cat "\$COMMIT_FILE")

EDITORSCRIPT

# Add message mappings to the editor script
# We match on the old subject line since SHA changes during rebase
declare -A OLD_SUBJECTS
OLD_SUBJECTS["31250d55c0d4157c255c044acc6d249930780527"]=$(git --no-pager log --format="%s" -1 31250d55c0d4157c255c044acc6d249930780527)
OLD_SUBJECTS["26d0d72501d4b89a5b207c8b1c920e1693eb2cc3"]=$(git --no-pager log --format="%s" -1 26d0d72501d4b89a5b207c8b1c920e1693eb2cc3)
OLD_SUBJECTS["57f99bae475e321ff088cf87241cd572471caddd"]=$(git --no-pager log --format="%s" -1 57f99bae475e321ff088cf87241cd572471caddd)
OLD_SUBJECTS["5d46c31f5b49862afa8dd83f9ccec805b182521e"]=$(git --no-pager log --format="%s" -1 5d46c31f5b49862afa8dd83f9ccec805b182521e)
OLD_SUBJECTS["e9cf60cacbacc92d329bba12caaf507c54589d8f"]=$(git --no-pager log --format="%s" -1 e9cf60cacbacc92d329bba12caaf507c54589d8f)

# Build the editor using a counter-based approach (rebase processes in order)
MSG_DIR=$(mktemp -d)
echo "${MESSAGES[31250d55c0d4157c255c044acc6d249930780527]}" > "$MSG_DIR/1.msg"
echo "${MESSAGES[26d0d72501d4b89a5b207c8b1c920e1693eb2cc3]}" > "$MSG_DIR/2.msg"
echo "${MESSAGES[57f99bae475e321ff088cf87241cd572471caddd]}" > "$MSG_DIR/3.msg"
echo "${MESSAGES[5d46c31f5b49862afa8dd83f9ccec805b182521e]}" > "$MSG_DIR/4.msg"
echo "${MESSAGES[e9cf60cacbacc92d329bba12caaf507c54589d8f]}" > "$MSG_DIR/5.msg"

COUNTER_FILE="$MSG_DIR/counter"
echo "1" > "$COUNTER_FILE"

cat > "$COMMIT_MSG_EDITOR" << EDITORSCRIPT
#!/usr/bin/env bash
COMMIT_FILE="\$1"
COUNTER_FILE="$COUNTER_FILE"
MSG_DIR="$MSG_DIR"

N=\$(cat "\$COUNTER_FILE")
MSG_FILE="\$MSG_DIR/\$N.msg"

if [ -f "\$MSG_FILE" ]; then
    cat "\$MSG_FILE" > "\$COMMIT_FILE"
fi

echo \$(( N + 1 )) > "\$COUNTER_FILE"
EDITORSCRIPT
chmod +x "$COMMIT_MSG_EDITOR"

echo ""
echo "Running interactive rebase..."
GIT_SEQUENCE_EDITOR="$SEQUENCE_EDITOR_SCRIPT" \
GIT_EDITOR="$COMMIT_MSG_EDITOR" \
git rebase -i HEAD~5

# Cleanup temp files
rm -f "$SEQUENCE_EDITOR_SCRIPT" "$COMMIT_MSG_EDITOR" "$REWRITE_MAP_FILE" "$COUNTER_FILE"
rm -rf "$MSG_DIR"

echo ""
echo "Done! New commit messages:"
echo "─────────────────────────────────────────────"
git --no-pager log --oneline -5
echo ""
echo "If satisfied, force-push with: git push --force-with-lease origin $BRANCH"
