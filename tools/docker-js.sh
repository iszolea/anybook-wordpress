#!/usr/bin/env bash
# Runs the block's JavaScript checks in Docker, so the PC needs no Node: npm ci, lint (JS and styles), the Jest
# unit tests, and a fresh build compared with the committed build/ (the release ships build/ as committed).
#
#   bash tools/docker-js.sh            check only: fails if build/ is not what the source builds to
#   WRITE=1 bash tools/docker-js.sh    also writes the fresh build into build/ (after changing blocks/)
#
# The source is copied into the container; node_modules lives in a Docker volume (anybook-wp-node) reused
# between runs. Containers are removed when they stop.
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/.."
SRC="$(pwd -W 2>/dev/null || pwd)"
VOL=anybook-wp-node
# The image is pinned (version and digest), so the build does not change under us.
IMAGE=node:20.20.2@sha256:8f693eaa7e0a8e71560c9a82b55fd54c2ae920a2ba5d2cde28bac7d1c01c9ba5

# The tool version the readme names (Source code and build) must be the one package.json pins.
README_SCRIPTS=$(sed -n 's/.*`@wordpress\/scripts` \([0-9][0-9.]*\).*/\1/p' readme.txt | head -1)
PKG_SCRIPTS=$(sed -n 's/.*"@wordpress\/scripts": "\([^"]*\)".*/\1/p' package.json | head -1)
if [ -z "$README_SCRIPTS" ] || [ "$README_SCRIPTS" != "$PKG_SCRIPTS" ]; then
	echo "docker-js: readme.txt names @wordpress/scripts '$README_SCRIPTS' but package.json pins '$PKG_SCRIPTS': make them the same" >&2
	exit 1
fi
COPY='mkdir -p /work && cd /src && tar --exclude=./vendor --exclude=./node_modules --exclude=./.git -cf - . | tar -xf - -C /work && cd /work'
OUT=()
FINISH='diff -r /tmp/build build && echo "== build/ matches the source"'
if [ -n "${WRITE:-}" ]; then
	OUT=(-v "$SRC/build:/out")
	FINISH='rm -rf /out/* && cp -r /tmp/build/. /out/ && echo "== build/ written"'
fi

docker run --rm -v "$SRC:/src:ro" -v "$VOL:/work/node_modules" "${OUT[@]}" "$IMAGE" sh -c "$COPY \
	&& echo '== npm ci' && npm ci --no-audit --no-fund --loglevel=error \
	&& echo '== lint' && npm run -s lint:js && npm run -s lint:style \
	&& echo '== unit tests' && npm run -s test:unit \
	&& echo '== build' && npx wp-scripts build --webpack-src-dir=blocks --output-path=/tmp/build >/dev/null \
	&& $FINISH"
echo "== all JS checks passed"
