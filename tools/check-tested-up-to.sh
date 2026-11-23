#!/usr/bin/env bash
# Fails when readme.txt's "Tested up to" is higher than the WordPress version it was tested on.
#   bash tools/check-tested-up-to.sh <readme.txt> <WordPress version, like 7.1.5>
# Only major.minor counts (7.1.5 satisfies "Tested up to: 7.1"). Used by tools/release.sh with the version from the
# test container (the official wordpress image the smoke test runs in).
set -euo pipefail
README=${1:?readme path}
WPVER=${2:?WordPress version}
TESTED=$(sed -n 's/^Tested up to: *//p' "$README" | tr -d '\r ')
[ -n "$TESTED" ] || { echo "check-tested-up-to: $README has no 'Tested up to'" >&2; exit 1; }
WPMM=$(printf '%s' "$WPVER" | cut -d. -f1,2)
HIGHEST=$(printf '%s\n%s\n' "$TESTED" "$WPMM" | sort -V | tail -1)
if [ "$HIGHEST" != "$WPMM" ]; then
	echo "check-tested-up-to: readme.txt says 'Tested up to: $TESTED' but the test container runs WordPress $WPVER: lower it, or test on $TESTED first" >&2
	exit 1
fi
echo "== Tested up to $TESTED is not above the tested WordPress $WPVER"
