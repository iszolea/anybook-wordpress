#!/usr/bin/env bash
# Release the plugin to the WordPress.org plugin directory (design Step 8, ADR-16). Nothing here runs by itself.
#
#   bash tools/release.sh --zip
#       Builds dist/anybook-<version>.zip from the committed tree (git archive: what export-ignore leaves), after
#       checking the versions agree and build/ matches the block's source. No network. This zip is what the owner
#       uploads once, by hand, at https://wordpress.org/plugins/developers/add/ for the first review.
#
#   bash tools/release.sh --submit --go-ahead "<who, when>"
#       Only after WordPress.org has approved the plugin and the owner has said to release this version. Builds the
#       zip, then commits it to https://plugins.svn.wordpress.org/anybook: trunk, the tag <version>, and the
#       directory's images from .wordpress-org/ (assets). The SVN password is read from Windmill (secret variable
#       f/anybook/wordpress_org_svn_password, made by the owner) and handed to svn on stdin: never printed, never on
#       disk, never in an argument. SVN user: ANYBOOK_SVN_USER (default anybookapp, the owner's WordPress.org account).
#
#   bash tools/release.sh --page --go-ahead "<who, when>"
#       Updates only the plugin's page, with no new version: commits readme.txt to trunk and to the existing Stable tag
#       directory (tags/<Stable tag>), and the images from .wordpress-org/ to assets. The code is not touched and no
#       tag is created. Use it for text, tags, banner and screenshot changes. Same password route as --submit.
#
# --submit refuses to run when SKIP_BUILD_CHECK is set. --zip and --submit fail when readme.txt's "Tested up to" is
# higher than the WordPress in the test container (tools/check-tested-up-to.sh).
# Without --zip, --submit or --page it refuses to run.
#
# On this Windows PC, run it with Git Bash (plain "bash" there is a broken WSL bash), from the plugin's folder:
#   "C:\Program Files\Git\bin\bash.exe" tools/release.sh --submit --go-ahead "<who, when>"
# It says each step as it goes; the build check takes one to three minutes and the SVN commit about a minute.
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/.."

SVN_URL=https://plugins.svn.wordpress.org/anybook
SVN_SECRET=f/anybook/wordpress_org_svn_password
SVN_USER=${ANYBOOK_SVN_USER:-anybookapp}
VPSMCP_DIR=${VPSMCP_DIR:-/c/Users/jnich/Projects/AiNativeHalo/tools}

usage() { sed -n '2,27p' "$0" | sed 's/^# \{0,1\}//'; exit "${1:-1}"; }
die() { echo "release: $*" >&2; exit 1; }

MODE="" GO_AHEAD=""
while [ $# -gt 0 ]; do
	case "$1" in
		--zip) MODE=${MODE:-zip} ;;
		--submit) MODE=submit ;;
		--page) MODE=page ;;
		--go-ahead) GO_AHEAD=${2:-}; shift ;;
		--go-ahead=*) GO_AHEAD=${1#*=} ;;
		-h|--help) usage 0 ;;
		*) die "unknown option $1" ;;
	esac
	shift
done
[ -n "$MODE" ] || { echo "release: refusing to run without --zip (build only), --submit (publish a version) or --page (update the plugin page only)." >&2; usage; }
# The first thing --submit does: the build check can be skipped to build a zip for looking, never to release.
if [ "$MODE" = submit ] && [ -n "${SKIP_BUILD_CHECK:-}" ]; then
	echo "SKIP_BUILD_CHECK is not allowed with --submit." >&2
	exit 1
fi
[ "$MODE" = zip ] || [ -n "$GO_AHEAD" ] || die "--submit and --page need --go-ahead \"<who, when>\": the owner's word to release this version"

# The committed tree only: what is released is what was reviewed.
[ -z "$(git status --porcelain)" ] || die "the working tree has changes; commit or set them aside first"
read_secret() {
	VPSMCP_DIR="$VPSMCP_DIR" python - "$SVN_SECRET" <<'PY'
import os, sys
sys.path.insert(0, os.environ["VPSMCP_DIR"])
import vpsmcp
vpsmcp._rpc("initialize", {"protocolVersion": "2025-03-26", "capabilities": {}, "clientInfo": {"name": "anybook-release", "version": "1"}})
value = vpsmcp.call("getVariable", workspace_id="admins", path=sys.argv[1], decrypt_secret=True)
value = value.get("value") if isinstance(value, dict) else value
if not value:
    sys.exit("the SVN password is not in Windmill yet (" + sys.argv[1] + ")")
sys.stdout.write(value)
PY
}

# --page: the page only (readme, tags, images). No version, build or zip checks: the code is not released.
if [ "$MODE" = page ]; then
	STABLE=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '
')
	[ -n "$STABLE" ] && [ "$STABLE" != trunk ] || die "readme.txt has no usable Stable tag"
	[ ! -e .wordpress-org/DRAFT.txt ] || die ".wordpress-org/ holds draft images the owner has not approved (DRAFT.txt)"
	echo "== updating the plugin page (readme in trunk and tags/$STABLE, and assets) at $SVN_URL as $SVN_USER (go-ahead: $GO_AHEAD)"
	SRC="$(pwd -W 2>/dev/null || pwd)"
	read_secret | docker run --rm -i -v "$SRC:/src:ro" -e STABLE="$STABLE" -e SVN_URL="$SVN_URL" -e SVN_USER="$SVN_USER" 		-e GO_AHEAD="$GO_AHEAD" alpine:3.20 sh -euc '
		apk add -q --no-cache subversion rsync >/dev/null
		IFS= read -r PASS || true
		[ -n "$PASS" ] || { echo "no SVN password" >&2; exit 1; }
		svnq() { printf "%s" "$PASS" | svn --non-interactive --no-auth-cache --username "$SVN_USER" --password-from-stdin "$@"; }
		cd /tmp && svnq checkout -q --depth immediates "$SVN_URL" svn
		cd svn && svnq update -q --set-depth infinity trunk assets "tags/$STABLE"
		[ -d "tags/$STABLE" ] || { echo "tag $STABLE does not exist in SVN: release it first" >&2; exit 1; }
		cp /src/readme.txt trunk/readme.txt
		cp /src/readme.txt "tags/$STABLE/readme.txt"
		if [ -d /src/.wordpress-org ]; then
			mkdir -p assets
			rsync -a --delete --exclude=.svn --include="*.png" --include="*.jpg" --include="*.svg" --exclude="*" /src/.wordpress-org/ assets/
		fi
		svn add -q --force assets
		svn status | grep "^!" | cut -c9- | while IFS= read -r gone; do svn rm -q "$gone"; done
		svn status
		svnq commit -q -m "Update plugin page: readme and assets, no new version (go-ahead: $GO_AHEAD)"
		echo "committed readme (trunk and tags/$STABLE) and assets"
	'
	exit 0
fi
VERSION=$(sed -n 's/^ \* Version: *//p' anybook.php | tr -d '\r')
STABLE=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '\r')
CONST=$(sed -n "s/.*define( 'ANYBOOK_VERSION', '\([^']*\)' ).*/\1/p" anybook.php)
BLOCK=$(sed -n 's/.*"version": "\([^"]*\)".*/\1/p' blocks/booking/block.json)
[ -n "$VERSION" ] && [ "$VERSION" = "$STABLE" ] && [ "$VERSION" = "$CONST" ] && [ "$VERSION" = "$BLOCK" ] \
	|| die "versions disagree: header $VERSION, readme Stable tag $STABLE, ANYBOOK_VERSION $CONST, block.json $BLOCK"
grep -q "^= $VERSION =" readme.txt || die "readme.txt has no changelog entry for $VERSION"
[ "$(git ls-files build/booking | wc -l)" -ge 4 ] || die "build/booking is not committed"
# "Tested up to" must not be higher than the WordPress the test container runs (the smoke test's image).
WP_TESTED=$(docker run --rm wordpress:latest php -r 'include "/usr/src/wordpress/wp-includes/version.php"; echo $wp_version;') \
	|| die "could not read the WordPress version from the test container"
bash tools/check-tested-up-to.sh readme.txt "$WP_TESTED" || die "Tested up to is higher than the WordPress that was tested"
if [ -z "${SKIP_BUILD_CHECK:-}" ]; then
	echo "== checking that build/ is what blocks/ builds to (lint, tests and a fresh build in Docker: one to three minutes)"
	JSLOG=$(mktemp)
	bash tools/docker-js.sh >"$JSLOG" 2>&1 || { tail -20 "$JSLOG" >&2; rm -f "$JSLOG"; die "build/ does not match blocks/, or the block's checks failed (above; run WRITE=1 bash tools/docker-js.sh and commit)"; }
	rm -f "$JSLOG"
	echo "== build/ matches blocks/"
fi

mkdir -p dist
ZIP=dist/anybook-$VERSION.zip
git archive --format=zip --prefix=anybook/ -o "$ZIP" HEAD
echo "== $ZIP ($(wc -c <"$ZIP") bytes, sha256 $(sha256sum "$ZIP" | cut -d' ' -f1)) from $(git rev-parse --short HEAD)"
[ "$MODE" = zip ] && exit 0

# --submit from here on. The directory's images are drafts until the owner approves them (then delete DRAFT.txt).
[ ! -e .wordpress-org/DRAFT.txt ] || die ".wordpress-org/ holds draft images the owner has not approved (DRAFT.txt)"
echo "== releasing $VERSION to $SVN_URL as $SVN_USER (go-ahead: $GO_AHEAD): reading the SVN password from Windmill, then checking out and committing (about a minute)"
SRC="$(pwd -W 2>/dev/null || pwd)"
# svn runs in a throwaway container; the password goes in on stdin only.
read_secret | docker run --rm -i -v "$SRC:/src:ro" -e VERSION="$VERSION" -e SVN_URL="$SVN_URL" -e SVN_USER="$SVN_USER" \
	-e GO_AHEAD="$GO_AHEAD" alpine:3.20 sh -euc '
	apk add -q --no-cache subversion unzip rsync >/dev/null
	IFS= read -r PASS || true
	[ -n "$PASS" ] || { echo "no SVN password" >&2; exit 1; }
	svnq() { printf "%s" "$PASS" | svn --non-interactive --no-auth-cache --username "$SVN_USER" --password-from-stdin "$@"; }
	cd /tmp && svnq checkout -q --depth immediates "$SVN_URL" svn
	cd svn && svnq update -q --set-depth infinity trunk assets
	if svnq ls "$SVN_URL/tags/$VERSION" >/dev/null 2>&1; then echo "tag $VERSION exists already" >&2; exit 1; fi
	unzip -q "/src/dist/anybook-$VERSION.zip" -d /tmp/release
	rsync -a --delete --exclude=.svn /tmp/release/anybook/ trunk/
	if [ -d /src/.wordpress-org ]; then
		mkdir -p assets
		rsync -a --delete --exclude=.svn --include="*.png" --include="*.jpg" --include="*.svg" --exclude="*" /src/.wordpress-org/ assets/
	fi
	svn add -q --force trunk assets
	svn status | grep "^!" | cut -c9- | while IFS= read -r gone; do svn rm -q "$gone"; done
	svn copy -q trunk "tags/$VERSION"
	svnq commit -q -m "Release $VERSION (go-ahead: $GO_AHEAD)"
	echo "committed $VERSION to trunk, tags/$VERSION and assets"
'
