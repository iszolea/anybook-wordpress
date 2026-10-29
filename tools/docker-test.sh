#!/usr/bin/env bash
# Runs the plugin's checks in Docker, so the PC needs no PHP: PHP lint on 7.4 and 8.3, PHPCS (WordPress Coding
# Standards and PHP 7.4 compatibility) and the PHPUnit unit tests on both versions.
#
#   bash tools/docker-test.sh
#
# The source is copied into the container (a Windows bind mount is too slow for Composer), and vendor/ lives in a
# Docker volume (anybook-wp-vendor) that is reused between runs. Containers are removed when they stop.
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/.."
SRC="$(pwd -W 2>/dev/null || pwd)" # pwd -W gives Docker Desktop a Windows path under Git Bash
VOL=anybook-wp-vendor
COPY='mkdir -p /work && cd /src && tar --exclude=./vendor --exclude=./node_modules --exclude=./.git -cf - . | tar -xf - -C /work && cd /work'
LINT='for f in $(find . -name "*.php" -not -path "./vendor/*"); do php -l "$f" >/dev/null || exit 1; done'

echo "== composer install"
docker run --rm -v "$SRC:/src:ro" -v "$VOL:/work/vendor" composer:2 sh -c "$COPY && composer install --no-interaction --no-progress -q"

for v in 7.4 8.3; do
	echo "== PHP $v: lint and unit tests"
	docker run --rm -v "$SRC:/src:ro" -v "$VOL:/work/vendor" "php:$v-cli" sh -c "$COPY && $LINT && vendor/bin/phpunit"
done

echo "== PHPCS (WordPress)"
docker run --rm -v "$SRC:/src:ro" -v "$VOL:/work/vendor" php:8.3-cli sh -c "$COPY && vendor/bin/phpcs"
echo "== all checks passed"
