#!/usr/bin/env bash
# Runs the block's JavaScript checks in Docker, so the PC needs no Node: npm ci, lint (JS and styles), the Jest
# unit tests, and a fresh build compared with the committed build/ (the release ships build/ as committed).
#
#   bash tools/docker-js.sh            check only: fails if build/ is not what the source builds to
#   WRITE=1 bash tools/docker-js.sh    also writes the fresh build into build/ (after changing blocks/)
#
# The source is copied into the container, and each run installs its own node_modules there. It is never shared: two
# runs at once, or one stopped half-way, broke the old shared node_modules volume ("npm error enoent"). What is shared
# is npm's download cache, a Docker volume (anybook-wp-npm-cache) that is safe for several runs at once. A run after the
# first needs no network for packages it has seen, and a dropped connection is retried. Containers are removed when they stop.
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/.."
SRC="$(pwd -W 2>/dev/null || pwd)"
CACHE=anybook-wp-npm-cache
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

# npm ci from the cache first (--prefer-offline), with npm's own retries for each download, and the whole install tried
# up to three times with a pause, so one slow or dropped connection does not fail the check. (Single quotes: the
# container's shell reads $n.)
INSTALL='n=1; until npm ci --no-audit --no-fund --loglevel=error --prefer-offline --fetch-retries=5 --fetch-retry-mintimeout=10000 --fetch-retry-maxtimeout=120000; do if [ "$n" -ge 3 ]; then exit 1; fi; echo "== npm ci failed (try $n of 3), again in 20 s"; n=$((n + 1)); sleep 20; rm -rf node_modules; done'

docker run --rm -v "$SRC:/src:ro" -v "$CACHE:/root/.npm" "${OUT[@]}" "$IMAGE" sh -c "$COPY \
	&& echo '== npm ci' && { $INSTALL; } \
	&& echo '== lint' && npm run -s lint:js && npm run -s lint:style \
	&& echo '== unit tests' && npm run -s test:unit \
	&& echo '== build' && npx wp-scripts build --webpack-src-dir=blocks --output-path=/tmp/build >/dev/null \
	&& $FINISH"
echo "== all JS checks passed"
