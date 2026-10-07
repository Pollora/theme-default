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
TARGET_DIR="$(dirname "$SCRIPT_DIR")"

# The dev theme code name and its StudlyCase variant
CODE_NAME="pollora-starter"
CODE_STUDLY="PolloraStarter"

echo "=== Packaging theme ==="
echo "  Source: $SOURCE"
echo "  Target: $TARGET_DIR"
echo ""

if [ ! -d "$SOURCE" ]; then
    echo "Error: Source directory not found: $SOURCE"
    exit 1
fi

# Views the development copy keeps for pollora-test's template-hierarchy tests
# (tests/Feature/Routing/TemplateHierarchyTest.php): they are fixtures, not part
# of the template, so they never leave the development copy.
TEST_FIXTURES=(
    resources/views/archive.blade.php
    resources/views/author.blade.php
    resources/views/category.blade.php
    resources/views/search.blade.php
    resources/views/single-project.blade.php
    resources/views/taxonomy.blade.php
)
FIXTURE_EXCLUDES=()
for fixture in "${TEST_FIXTURES[@]}"; do
    FIXTURE_EXCLUDES+=(--exclude="/$fixture")
done

# Sync files
echo "Syncing files..."
rsync -av --delete \
    --exclude='node_modules' \
    --exclude='package-lock.json' \
    --exclude='yarn.lock' \
    --exclude='.git' \
    --exclude='package-theme.sh' \
    --exclude='bin/' \
    --exclude='/README.md' \
    --exclude='/.github/' \
    --exclude='/LICENSE' \
    --exclude='/license.txt' \
    "${FIXTURE_EXCLUDES[@]}" \
    "$SOURCE/" "$TARGET_DIR/" \
    --quiet

# package.json comes from the development copy, which declares no license (or
# another one): the template's is MIT, the same as LICENSE.
echo "Setting the package.json license to MIT..."
node -e '
const fs = require("fs");
const [file, license] = process.argv.slice(1);
const pkg = JSON.parse(fs.readFileSync(file, "utf8"));
let out = pkg;
if ("license" in pkg) {
    pkg.license = license;
} else {
    out = {};
    for (const [key, value] of Object.entries(pkg)) {
        out[key] = value;
        if (key === "private") out.license = license;
    }
    if (!("license" in out)) out.license = license;
}
fs.writeFileSync(file, JSON.stringify(out, null, 4) + "\n");
' "$TARGET_DIR/package.json" "MIT"

echo "Replacing code name with placeholders..."

# README.md documents the packaging workflow itself, so it names the code name
# on purpose. Every other file must come out of here carrying placeholders only.
find "$TARGET_DIR" -type f \
    -not -path "*/.git/*" \
    -not -path "*/.github/*" \
    -not -path "*/bin/*" \
    -not -path "*/node_modules/*" \
    -not -name "package-theme.sh" \
    -not -name "README.md" \
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

        # Catch-all, last.
        #
        # The rules above are anchored on a surrounding phrase, which means a
        # new file shape is silently left alone: the blocks added in
        # resources/views/blocks went out in v1.4.0 still naming
        # "%theme_name%/hero", "%theme_name%" as their text domain and
        # .wp-block-%theme_name%-hero as their class, because no anchored rule
        # matched JSON, JSX or CSS. The slug is unique enough to replace
        # wherever it appears, so anything the anchored rules miss lands here
        # rather than in a published tag.
        #
        # There is deliberately no equivalent for the StudlyCase variant: the
        # only placeholder for it is %theme_namespace%, which expands to
        # "Theme\Something" and is handled by the first anchored rule.
        sed -i "s|${CODE_NAME}|%theme_name%|g" "$file"
    done

echo "Checking nothing kept the code name..."

if leaked=$(grep -rn "${CODE_NAME}\|${CODE_STUDLY}" "$TARGET_DIR" \
        --exclude-dir=.git --exclude-dir=.github --exclude-dir=bin --exclude-dir=node_modules --exclude=README.md --exclude=package-theme.sh); then
    echo ""
    echo "Error: the code name survived packaging:"
    echo "$leaked"
    exit 1
fi

echo ""
echo "=== Done ==="
echo ""
echo "Review changes:"
echo "  cd $TARGET_DIR && git diff"
echo ""
echo "Then commit, tag and push:"
echo "  git add -A && git commit -m 'feat: update theme template'"
echo "  git tag x.y.z && git push origin main --tags"