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
# Without --zip or --submit it refuses to run.
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/.."

SVN_URL=https://plugins.svn.wordpress.org/anybook
SVN_SECRET=f/anybook/wordpress_org_svn_password
SVN_USER=${ANYBOOK_SVN_USER:-anybookapp}
VPSMCP_DIR=${VPSMCP_DIR:-/c/Users/jnich/Projects/AiNativeHalo/tools}

usage() { sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'; exit "${1:-1}"; }
die() { echo "release: $*" >&2; exit 1; }

MODE="" GO_AHEAD=""
while [ $# -gt 0 ]; do
	case "$1" in
		--zip) MODE=${MODE:-zip} ;;
		--submit) MODE=submit ;;
		--go-ahead) GO_AHEAD=${2:-}; shift ;;
		--go-ahead=*) GO_AHEAD=${1#*=} ;;
		-h|--help) usage 0 ;;
		*) die "unknown option $1" ;;
	esac
	shift
done
[ -n "$MODE" ] || { echo "release: refusing to run without --zip (build only) or --submit (publish to WordPress.org)." >&2; usage; }
[ "$MODE" != submit ] || [ -n "$GO_AHEAD" ] || die "--submit needs --go-ahead \"<who, when>\": the owner's word to release this version"

# The committed tree only: what is released is what was reviewed.
[ -z "$(git status --porcelain)" ] || die "the working tree has changes; commit or set them aside first"
VERSION=$(sed -n 's/^ \* Version: *//p' anybook.php | tr -d '\r')
STABLE=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '\r')
CONST=$(sed -n "s/.*define( 'ANYBOOK_VERSION', '\([^']*\)' ).*/\1/p" anybook.php)
BLOCK=$(sed -n 's/.*"version": "\([^"]*\)".*/\1/p' blocks/booking/block.json)
[ -n "$VERSION" ] && [ "$VERSION" = "$STABLE" ] && [ "$VERSION" = "$CONST" ] && [ "$VERSION" = "$BLOCK" ] \
	|| die "versions disagree: header $VERSION, readme Stable tag $STABLE, ANYBOOK_VERSION $CONST, block.json $BLOCK"
grep -q "^= $VERSION =" readme.txt || die "readme.txt has no changelog entry for $VERSION"
[ "$(git ls-files build/booking | wc -l)" -ge 4 ] || die "build/booking is not committed"
if [ -z "${SKIP_BUILD_CHECK:-}" ]; then
	bash tools/docker-js.sh >/dev/null || die "build/ does not match blocks/ (run WRITE=1 bash tools/docker-js.sh and commit)"
fi

mkdir -p dist
ZIP=dist/anybook-$VERSION.zip
git archive --format=zip --prefix=anybook/ -o "$ZIP" HEAD
echo "== $ZIP ($(wc -c <"$ZIP") bytes, sha256 $(sha256sum "$ZIP" | cut -d' ' -f1)) from $(git rev-parse --short HEAD)"
[ "$MODE" = zip ] && exit 0

# --submit from here on. The directory's images are drafts until the owner approves them (then delete DRAFT.txt).
[ ! -e .wordpress-org/DRAFT.txt ] || die ".wordpress-org/ holds draft images the owner has not approved (DRAFT.txt)"
echo "== releasing $VERSION to $SVN_URL as $SVN_USER (go-ahead: $GO_AHEAD)"
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
	svn status | awk "/^!/ {print \$2}" | xargs -r svn rm -q
	svn copy -q trunk "tags/$VERSION"
	svnq commit -q -m "Release $VERSION (go-ahead: $GO_AHEAD)"
	echo "committed $VERSION to trunk, tags/$VERSION and assets"
'
