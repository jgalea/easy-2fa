#!/usr/bin/env bash
# Produces sigil-2fa.zip for wordpress.org submission.
#
# Ships everything at the plugin root EXCEPT dev tooling and test scaffolding.
# vendor/ is deliberately kept: lbuchs/webauthn is a runtime dependency the
# passkey provider loads at request time, not a build-time-only package.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
SOURCE_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/sigil-2fa.php" | head -1)"
RELEASE_VERSION="${SIGIL_RELEASE_VERSION:-${SOURCE_VERSION}}"
if [ -z "$RELEASE_VERSION" ]; then
	echo "Could not resolve a version from sigil-2fa.php" >&2
	exit 1
fi

rm -rf "$DIST/sigil-2fa" "$DIST/sigil-2fa.zip"
mkdir -p "$DIST/sigil-2fa"

rsync -a \
    --exclude='pro/' \
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
    --exclude='.wordpress-org/' \
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
    --exclude='.secret-scan-allow' \
    --exclude='vendor/' \
    --exclude='languages/' \
    "$ROOT/" "$DIST/sigil-2fa/"

# Translations are not shipped. Plugins hosted on wordpress.org receive their
# .po/.mo/.l10n.php files from translate.wordpress.org, so bundling compiled
# catalogues here would shadow the community ones. The .pot is kept as the
# template translators work from; everything else in languages/ stays in the
# repo for the GlotPress import and never enters the zip.
mkdir -p "$DIST/sigil-2fa/languages"
cp "$ROOT/languages/sigil-2fa.pot" "$DIST/sigil-2fa/languages/"

# vendor/ is excluded wholesale above because a local `composer install` fills it
# with PHPUnit and the Composer autoloader, none of which ships. The passkey
# provider loads lbuchs/webauthn by direct require, so that one committed package
# is the only vendored code the plugin needs at runtime.
mkdir -p "$DIST/sigil-2fa/vendor"
rsync -a --exclude='*.md' "$ROOT/vendor/lbuchs" "$DIST/sigil-2fa/vendor/"

MAIN="$DIST/sigil-2fa/sigil-2fa.php"
README="$DIST/sigil-2fa/readme.txt"

python3 - "$MAIN" "$README" "$RELEASE_VERSION" <<'PY'
import re
import sys

main_path, readme_path, version = sys.argv[1], sys.argv[2], sys.argv[3]

with open(main_path, 'r', encoding='utf-8') as f:
    main = f.read()
main = re.sub(r"(?m)^(\s*\*\s*Version:\s*).+$", r"\g<1>" + version, main, count=1)
main = re.sub(r"define\(\s*'SIGIL_VERSION',\s*'[^']+'\s*\);", f"define( 'SIGIL_VERSION', '{version}' );", main, count=1)
with open(main_path, 'w', encoding='utf-8') as f:
    f.write(main)

with open(readme_path, 'r', encoding='utf-8') as f:
    readme = f.read()
readme = re.sub(r"(?m)^Stable tag:.*$", f"Stable tag: {version}", readme, count=1)
with open(readme_path, 'w', encoding='utf-8') as f:
    f.write(readme)
PY

cd "$DIST"
zip -rqX sigil-2fa.zip sigil-2fa/

echo "Built: $DIST/sigil-2fa.zip (version $RELEASE_VERSION)"
du -h "$DIST/sigil-2fa.zip" | awk '{print "Size:", $1}'
