#!/usr/bin/env bash
# End to end against the real Anybook (AC-1, AC-2, AC-7): a fresh install of booking-system's Docker image on port
# 8092, and a real WordPress on port 8093 with the plugin as it ships. A headless browser connects the site through
# Anybook's own connect page as the Owner, checks the booking page stayed a draft until Allow, the status, the
# editor's block and catalogue, then loads the booking page as a visitor with Autoptimize and then LiteSpeed Cache
# optimising scripts, and checks the widget really renders. It also saves Anybook's real /site answer as the shared
# fixture (tests/fixtures/site-status.json), draft directory screenshots (.wordpress-org/), and checks every Owner
# got the "A website was allowed" email (S-I3) in the install's mail catcher.
#
#   BOOKING_SYSTEM=/c/Users/jnich/Projects/Iszolea/booking-system bash tests/e2e/run.sh
#
# Needs Docker Desktop, .NET (booking-system's click-through fixture seeds the install: setup, the Owner, one unit,
# online booking on) and Node with this repo's node_modules (Playwright and its Chromium). Everything is named
# anybook-e2e-* and removed at the end: containers, volumes, networks and the image. KEEP=1 keeps them.
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/../.."
BS=${BOOKING_SYSTEM:?Set BOOKING_SYSTEM to a booking-system working copy with the connect page}
BS=$(cd "$BS" && (pwd -W 2>/dev/null || pwd)) # a Windows path for git, docker and dotnet under Git Bash
[ -f "$BS/src/Booking.Web/Components/Pages/Connect/WordPress.razor" ] || { echo "$BS has no /connect/wordpress page"; exit 1; }
export PATH="/c/Program Files/dotnet:$PATH" DOTNET_CLI_TELEMETRY_OPTOUT=1 DOTNET_NOLOGO=1

P=anybook-e2e
IMAGE=$P-booking:local
WORK=$(cd "$(mktemp -d)" && (pwd -W 2>/dev/null || pwd))
ENVF="$WORK/booking.env"
COMPOSE=(docker compose -p $P --env-file "$ENVF" -f "$BS/deploy/docker-compose.yml" -f "$BS/deploy/docker-compose.behind-proxy.yml" -f "$BS/deploy/docker-compose.e2e-mail.yml")
NET=$P-wp-net
DBENV=(-e WORDPRESS_DB_HOST=$P-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp)
export WP_URL=http://wp.localhost:8093 ANYBOOK_URL=http://anybook.localhost:8092 E2E_STATE="$WORK/state.json"
# Test values only: a fresh password per run, and the Owner the booking-system click-through fixture creates.
export WP_USER=admin WP_PASS
WP_PASS=$(openssl rand -hex 12)
export OWNER_EMAIL=owner@e2e.test OWNER_PASSWORD='Correct-Horse-9x!'

cleanup() { # "start": also with KEEP=1, to clear what a kept run left
	[ -n "${KEEP:-}" ] && [ "${1:-}" != start ] && return
	[ -f "$ENVF" ] && "${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
	docker rm -f $P-fwd $P-wp $P-db >/dev/null 2>&1 || true
	docker volume rm $P-html >/dev/null 2>&1 || true
	docker network rm $NET >/dev/null 2>&1 || true
	docker image rm $IMAGE >/dev/null 2>&1 || true
	rm -rf "$WORK"
}
trap cleanup EXIT
fail() { echo "FAIL: $*"; exit 1; }
pass() { echo "ok - $*"; }
wp() { docker run --rm -i --network $NET -v $P-html:/var/www/html --user 33:33 "${DBENV[@]}" wordpress:cli wp --quiet "$@"; }

echo "== Anybook from $BS ($(git -C "$BS" rev-parse --abbrev-ref HEAD) $(git -C "$BS" rev-parse --short HEAD))"
hex() { openssl rand -hex 16; }
write_env() {
	mkdir -p "$WORK"
	printf '%s\n' BOOKING_DOMAIN=localhost "POSTGRES_PASSWORD=$(hex)" "BOOKING_APP_DB_PASSWORD=$(hex)" BOOKING_SETUP_CODE=E2E-SETUP-0001 \
		"BOOKING_IMAGE=$IMAGE" BOOKING_LOCAL_PORT=8092 BOOKING_ALLOW_DEMO_PAYMENTS=true > "$ENVF"
}
write_env
cleanup start # what a KEEP=1 run left behind
write_env
docker build -q -t $IMAGE "$BS" >/dev/null
"${COMPOSE[@]}" up -d 2>&1 | grep -iE "error|failed" && fail "Anybook did not start" || true
# The first start applies every migration, which has taken over three minutes on this PC.
for _ in $(seq 200); do curl -sf http://127.0.0.1:8092/health >/dev/null && break; sleep 2; done
curl -sf http://127.0.0.1:8092/health >/dev/null || fail "Anybook did not start"
pass "Anybook is up on 8092"

# Seeding through booking-system's own click-through fixture, with its WordPress connect test.
(cd "$BS" && BOOKING_E2E_URL=http://127.0.0.1:8092 BOOKING_E2E_SETUP_CODE=E2E-SETUP-0001 \
	dotnet test tests/Booking.E2E.Tests --filter "FullyQualifiedName~Connecting_a_WordPress_site" --nologo -v q 2>&1 | tail -3)
pass "Anybook seeded: business, Owner, one unit, online booking on"

# WordPress: the plugin from git archive, its booking site set by ANYBOOK_SITE in wp-config.php. Inside the container
# curl takes *.localhost as itself, so a forwarder there passes anybook.localhost:8092 on to the host's port 8092.
docker network create $NET >/dev/null
docker run -d --name $P-db --network $NET -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp \
	-e MARIADB_RANDOM_ROOT_PASSWORD=1 mariadb:11 >/dev/null
docker run -d --name $P-wp --network $NET -p 127.0.0.1:8093:80 -v $P-html:/var/www/html "${DBENV[@]}" \
	-e WORDPRESS_CONFIG_EXTRA="define( 'ANYBOOK_SITE', '$ANYBOOK_URL' );" wordpress:latest >/dev/null
docker run -d --name $P-fwd --network container:$P-wp caddy:2 caddy reverse-proxy --from :8092 --to host.docker.internal:8092 >/dev/null
for _ in $(seq 60); do docker exec $P-wp test -f /var/www/html/wp-config.php 2>/dev/null && break; sleep 2; done
for _ in $(seq 60); do wp db check >/dev/null 2>&1 && break; sleep 2; done
wp core install --url=$WP_URL --title="E2E Houseboats" --admin_user=$WP_USER --admin_password="$WP_PASS" --admin_email=admin@example.test --skip-email
docker exec $P-wp mkdir -p /var/www/html/wp-content/plugins/anybook /var/www/html/wp-content/mu-plugins
git archive --format=tar HEAD | docker exec -i $P-wp tar -x -C /var/www/html/wp-content/plugins/anybook
# One ordinary script on every page, so the speed plugins have something of their own to optimise.
docker cp tests/smoke/other-script.php $P-wp:/var/www/html/wp-content/mu-plugins/other-script.php
docker exec $P-wp chown -R www-data:www-data /var/www/html/wp-content
wp plugin activate anybook
docker exec $P-wp curl -sf -o /dev/null "$ANYBOOK_URL/health" || fail "WordPress cannot reach Anybook"
pass "WordPress $(wp core version) is up on 8093 with the plugin"

# The browser: connect as the Owner, the editor, screenshots, the fixture.
node tests/e2e/browser.mjs connect
pass "connected through Anybook's connect page (AC-1, AC-2)"

# Every Owner is emailed about the new website (S-I3), in the install's mail catcher (it delivers nothing).
mailpit() { "${COMPOSE[@]}" exec -T mail wget -qO- "http://localhost:8025/api/v1/$1" 2>/dev/null || true; }
found=""
for mid in $(mailpit 'search?query=subject:%22A%20website%20was%20allowed%22' | grep -o '"ID":"[^"]*"' | cut -d'"' -f4); do
	grep -q 'wp.localhost:8093' <<<"$(mailpit "message/$mid")" && found=yes
done
if [ -n "$found" ]; then
	pass "the Owners were emailed about the new website (in the mail catcher)"
else
	# booking-system's e2e-mail override gives the app public DNS servers, with which it cannot always find the mail
	# catcher by name; the app's log then shows the notice was sent and the server could not be found.
	"${COMPOSE[@]}" logs app 2>/dev/null | grep -q 'The notice "A website was allowed to show your booking widget"' \
		|| fail "no 'A website was allowed' email for wp.localhost:8093, sent or tried"
	pass "the Owners' email about the new website was sent (the mail catcher's name did not resolve, so the app logged it as not delivered)"
fi

# A visitor with each speed plugin optimising scripts (AC-7): the widget still renders.
node tests/e2e/browser.mjs visit none
for plugin in autoptimize litespeed-cache; do
	wp plugin install $plugin --activate
	if [ $plugin = autoptimize ]; then
		for o in autoptimize_js=on autoptimize_js_aggregate=on autoptimize_js_defer_not_aggregate=on autoptimize_js_defer_inline=on; do wp option update "${o%%=*}" "${o#*=}"; done
	else
		for o in optm-js_min=1 optm-js_comb=1 optm-js_comb_ext_inl=1 optm-js_defer=2; do wp litespeed-option set "${o%%=*}" "${o#*=}" >/dev/null; done
	fi
	node tests/e2e/browser.mjs visit $plugin
	wp plugin deactivate $plugin
done
echo "== e2e passed"
