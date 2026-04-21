#!/bin/bash
set -euo pipefail

# Package the pollora-starter development theme into the theme-default template.
#
# Usage: ./package-theme.sh <source-theme-path>
#
# Example:
#   ./package-theme.sh ~/Sites/pollora-test/themes/pollora-starter
#
# This script:
# 1. Copies theme files (excluding node_modules, locks, build artifacts)
# 2. Replaces "pollora-starter" / "PolloraStarter" with placeholders
# 3. Shows a diff for review before committing

SOURCE="${1:?Usage: $0 <source-theme-path>}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# The dev theme code name and its StudlyCase variant
CODE_NAME="pollora-starter"
CODE_STUDLY="PolloraStarter"

echo "=== Packaging theme ==="
echo "  Source: $SOURCE"
echo "  Target: $SCRIPT_DIR"
echo ""

if [ ! -d "$SOURCE" ]; then
    echo "Error: Source directory not found: $SOURCE"
    exit 1
fi

# Sync files
echo "Syncing files..."
rsync -av --delete \
    --exclude='node_modules' \
    --exclude='package-lock.json' \
    --exclude='yarn.lock' \
    --exclude='.git' \
    --exclude='package-theme.sh' \
    "$SOURCE/" "$SCRIPT_DIR/" \
    --quiet

echo "Replacing code name with placeholders..."

find "$SCRIPT_DIR" -type f \
    -not -path "*/.git/*" \
    -not -path "*/node_modules/*" \
    -not -name "package-theme.sh" \
    -not -name "*.woff2" \
    -not -name "*.png" \
    -not -name "*.jpg" \
    -not -name "*.svg" \
    | while read -r file; do
        sed -i \
            -e "s|Theme\\\\${CODE_STUDLY}|%theme_namespace%|g" \
            -e "s|Theme Name: ${CODE_NAME}|Theme Name: %theme_name%|g" \
            -e "s|'name' => '${CODE_NAME}'|'name' => '%theme_name%'|g" \
            -e "s|'${CODE_NAME}/|'%theme_name%/|g" \
            -e "s|${CODE_STUDLY} Theme Functions|%theme_name% Theme Functions|g" \
            -e "s|${CODE_NAME} Theme Functions|%theme_name% Theme Functions|g" \
            -e "s|register ${CODE_STUDLY} theme|register %theme_name% theme|g" \
            -e "s|register ${CODE_NAME} theme|register %theme_name% theme|g" \
            -e "s|Theme URI: https://pollora.dev|Theme URI: %theme_uri%|g" \
            -e "s|Description: Pollora starter theme|Description: %theme_description%|g" \
            -e "s|Author: Pollora|Author: %theme_author%|g" \
            -e "s|Author URI: https://pollora.dev|Author URI: %theme_author_uri%|g" \
            -e "s|Version: [0-9.]*|Version: %theme_version%|g" \
            "$file"
    done

echo ""
echo "=== Done ==="
echo ""
echo "Review changes:"
echo "  cd $SCRIPT_DIR && git diff"
echo ""
echo "Then commit, tag and push:"
echo "  git add -A && git commit -m 'feat: update theme template'"
echo "  git tag x.y.z && git push origin main --tags"