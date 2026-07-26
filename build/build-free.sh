#!/usr/bin/env bash
# Produces easy-2fa.zip for wordpress.org submission.
#
# Ships everything at the plugin root EXCEPT dev tooling and test scaffolding.
# vendor/ is deliberately kept: lbuchs/webauthn is a runtime dependency the
# passkey provider loads at request time, not a build-time-only package.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
SOURCE_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/easy-2fa.php" | head -1)"
RELEASE_VERSION="${EASY2FA_RELEASE_VERSION:-${SOURCE_VERSION}}"
if [ -z "$RELEASE_VERSION" ]; then
	echo "Could not resolve a version from easy-2fa.php" >&2
	exit 1
fi

rm -rf "$DIST/easy-2fa" "$DIST/easy-2fa.zip"
mkdir -p "$DIST/easy-2fa"

rsync -a \
    --exclude='build/' \
    --exclude='dist/' \
    --exclude='docs/' \
    --exclude='e2e/' \
    --exclude='tests/' \
    --exclude='node_modules/' \
    --exclude='test-results/' \
    --exclude='playwright-report/' \
    --exclude='.git/' \
    --exclude='.gitignore' \
    --exclude='.github/' \
    --exclude='.distignore' \
    --exclude='.claude/' \
    --exclude='.wp-env.json' \
    --exclude='phpunit.xml.dist' \
    --exclude='playwright.config.ts' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='*.log' \
    --exclude='*.md' \
    --exclude='.DS_Store' \
    --exclude='.phpunit.result.cache' \
    --exclude='vendor/' \
    "$ROOT/" "$DIST/easy-2fa/"

# vendor/ is excluded wholesale above because a local `composer install` fills it
# with PHPUnit and the Composer autoloader, none of which ships. The passkey
# provider loads lbuchs/webauthn by direct require, so that one committed package
# is the only vendored code the plugin needs at runtime.
mkdir -p "$DIST/easy-2fa/vendor"
rsync -a --exclude='*.md' "$ROOT/vendor/lbuchs" "$DIST/easy-2fa/vendor/"

MAIN="$DIST/easy-2fa/easy-2fa.php"
README="$DIST/easy-2fa/readme.txt"

python3 - "$MAIN" "$README" "$RELEASE_VERSION" <<'PY'
import re
import sys

main_path, readme_path, version = sys.argv[1], sys.argv[2], sys.argv[3]

with open(main_path, 'r', encoding='utf-8') as f:
    main = f.read()
main = re.sub(r"(?m)^(\s*\*\s*Version:\s*).+$", r"\g<1>" + version, main, count=1)
main = re.sub(r"define\(\s*'EASY2FA_VERSION',\s*'[^']+'\s*\);", f"define( 'EASY2FA_VERSION', '{version}' );", main, count=1)
with open(main_path, 'w', encoding='utf-8') as f:
    f.write(main)

with open(readme_path, 'r', encoding='utf-8') as f:
    readme = f.read()
readme = re.sub(r"(?m)^Stable tag:.*$", f"Stable tag: {version}", readme, count=1)
with open(readme_path, 'w', encoding='utf-8') as f:
    f.write(readme)
PY

mkdir -p "$DIST/easy-2fa/languages"

cd "$DIST"
zip -rqX easy-2fa.zip easy-2fa/

echo "Built: $DIST/easy-2fa.zip (version $RELEASE_VERSION)"
du -h "$DIST/easy-2fa.zip" | awk '{print "Size:", $1}'
