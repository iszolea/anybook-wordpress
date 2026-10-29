#!/usr/bin/env bash
# A smoke test in a real WordPress (latest official Docker image, MariaDB) with Anybook faked by a must-use plugin:
#   activation makes no call to Anybook; connect return; settings page renders; the booking page is made and its
#   head loads the widget script deferred, unversioned and with the speed-plugin attributes; a page without the
#   widget loads nothing; Plugin Check; then multisite: uninstall clears every site and keeps the booking page.
#
#   bash tests/smoke/smoke.sh            (the committed tree, as git archive packs it for release)
#
# Everything runs in throwaway containers, a network and volumes named anybook-smoke-*, removed at the end.
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/../.."

P=anybook-smoke
NET=$P-net
DBENV=(-e WORDPRESS_DB_HOST=$P-db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp)
cleanup() {
	docker rm -f $P-db $P-wp >/dev/null 2>&1 || true
	docker volume rm $P-html >/dev/null 2>&1 || true
	docker network rm $NET >/dev/null 2>&1 || true
}
trap cleanup EXIT; [ -n "${KEEP:-}" ] && trap - EXIT
cleanup

wp() { docker run --rm -i --network $NET -v $P-html:/var/www/html --user 33:33 "${DBENV[@]}" wordpress:cli wp --quiet "$@"; }
page() { docker exec $P-wp curl -fsS "http://localhost$1"; }
fail() { echo "FAIL: $*"; exit 1; }
pass() { echo "ok - $*"; }

docker network create $NET >/dev/null
docker run -d --name $P-db --network $NET -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp \
	-e MARIADB_RANDOM_ROOT_PASSWORD=1 mariadb:11 >/dev/null
docker run -d --name $P-wp --network $NET -v $P-html:/var/www/html "${DBENV[@]}" wordpress:latest >/dev/null
for _ in $(seq 60); do docker exec $P-wp test -f /var/www/html/wp-config.php 2>/dev/null && break; sleep 2; done
for _ in $(seq 60); do wp db check >/dev/null 2>&1 && break; sleep 2; done

wp core install --url=http://localhost --title="Smoke & Co" --admin_user=admin --admin_password="$(openssl rand -hex 12)" \
	--admin_email=admin@example.test --skip-email

# The plugin as it would ship (export-ignore drops tests and tooling), and the fake Anybook.
docker exec $P-wp mkdir -p /var/www/html/wp-content/plugins/anybook /var/www/html/wp-content/mu-plugins
git archive --format=tar HEAD | docker exec -i $P-wp tar -x -C /var/www/html/wp-content/plugins/anybook
docker cp tests/smoke/fake-anybook.php $P-wp:/var/www/html/wp-content/mu-plugins/fake-anybook.php
docker exec $P-wp chown -R www-data:www-data /var/www/html/wp-content

wp plugin activate anybook
[ "$(wp option get fake_anybook_calls 2>/dev/null || echo 0)" = "0" ] || fail "Anybook was called on activation"
pass "activated with no call to Anybook"

# Connect: start as the admin, come back with the state Anybook carries.
out=$(wp eval '
	$url = Anybook\Connection::start( 1, "", false );
	parse_str( parse_url( $url, PHP_URL_QUERY ), $q );
	echo Anybook\Connection::handle_return( array( "state" => $q["state"], "anybook_slug" => "bribie" ), 1 ), "|", $q["return"];
	echo "|", Anybook\Connection::handle_return( array( "state" => $q["state"], "anybook_slug" => "bribie" ), 1 );')
[ "$out" = "connected|http://localhost/wp-admin/admin.php?page=anybook|bad-state" ] || fail "connect return: $out"
pass "connect return saved the business; the state works once"

html=$(wp eval 'wp_set_current_user( 1 ); require_once ABSPATH . "wp-admin/includes/template.php"; Anybook\Admin::render_page();')
grep -q 'Connected to Bribie Houseboat Holidays' <<<"$html" || fail "settings page has no connected line"
grep -q 'Set your guest page' <<<"$html" || fail "settings page does not offer to set the guest page"
grep -q 'script-src https://app.anybookapp.com' <<<"$html" || fail "settings page has no CSP lines"
pass "settings page renders the status, guest page offer and CSP lines"

id=$(wp eval 'echo Anybook\Booking_Page::ensure_published();')
body=$(page "/?page_id=$id")
head=${body%%</head>*}
grep -q '<booking-widget business="bribie" api="https://app.anybookapp.com" guest-links></booking-widget>' <<<"$body" \
	|| fail "booking page has no widget element"
tag=$(grep -o '<script[^>]*booking-widget\.js[^>]*>' <<<"$head" || true)
[ -n "$tag" ] || fail "widget script not in the head of the booking page"
for want in 'src="https://app.anybookapp.com/widget/booking-widget.js"' 'defer' 'data-cfasync="false"' 'data-no-optimize="1"' 'nowprocket'; do
	grep -qF "$want" <<<"$tag" || fail "script tag lacks $want: $tag"
done
grep -q 'booking-widget\.js?ver' <<<"$tag" && fail "script has a version: $tag"
[ "$(grep -o 'booking-widget\.js' <<<"$body" | wc -l)" = "1" ] || fail "script loaded more than once"
pass "booking page: element, script in the head, defer, no ?ver, speed-plugin attributes, once"

[ "$(wp post get "$id" --field=post_content)" = '<!-- wp:anybook/booking {"guestLinks":true} /-->' ] 	|| fail "booking page on the block theme does not hold the block"
pass "booking page holds the Anybook booking block (block theme)"

# The block and the shortcode with the same choices give the same element.
blk=$(wp post create --post_type=page --post_status=publish --post_title=Block --porcelain 	--post_content='<!-- wp:anybook/booking {"show":"service","service":"cut","accent":"#0B6E4F","buttonText":"Book now"} /-->')
sc=$(wp post create --post_type=page --post_status=publish --post_title=Short --porcelain 	--post_content='[anybook service="cut" accent="#0B6E4F" button_text="Book now"]')
want='<booking-widget business="bribie" api="https://app.anybookapp.com" service="cut" services texts="{&quot;toPayment&quot;:&quot;Book now&quot;}" style="--bw-accent:#0b6e4f;"></booking-widget>'
page "/?page_id=$blk" | grep -qF "$want" || fail "block output: $(page "/?page_id=$blk" | grep -o '<booking-widget[^>]*>')"
page "/?page_id=$sc" | grep -qF "$want" || fail "shortcode output: $(page "/?page_id=$sc" | grep -o '<booking-widget[^>]*>')"
pass "block and shortcode give the same element"

wp eval 'echo WP_Block_Type_Registry::get_instance()->is_registered( "anybook/booking" ) ? "yes" : "no";' | grep -qx yes 	|| fail "block not registered"
cat=$(wp eval 'wp_set_current_user( 1 ); $r = rest_do_request( new WP_REST_Request( "GET", "/anybook/v1/catalog" ) ); echo $r->get_status(), " ", wp_json_encode( $r->get_data() );')
[ "$cat" = '200 {"units":[{"id":"u1","code":"belle","name":"The Belle"}],"services":[{"id":"s1","code":"cut","name":"Haircut"}]}' ] 	|| fail "catalog route: $cat"
anon=$(wp eval 'wp_set_current_user( 0 ); echo rest_do_request( new WP_REST_Request( "GET", "/anybook/v1/catalog" ) )->get_status();')
[ "$anon" = "401" ] || fail "catalog route open to visitors: $anon"
pass "block registered; catalogue route answers editors only"

other=$(wp post create --post_type=post --post_status=publish --post_title=Hello --post_content='No widget' --porcelain)
page "/?p=$other" | grep -q 'booking-widget' && fail "page without the widget loads it"
pass "a page without the widget loads no script"

# Plugin Check (from wordpress.org) on the shipped tree.
wp plugin install plugin-check --activate
pc=$(wp plugin check anybook --format=csv 2>&1 || true)
echo "$pc" | sed 's/^/  plugin-check: /'
grep -qi ',ERROR,' <<<"$pc" && fail "Plugin Check found errors"
pass "Plugin Check: no errors"

# Multisite: two sites each with settings; uninstall clears both and keeps the booking page.
wp core multisite-convert --title="Smoke network"
wp site create --slug=two
wp eval 'switch_to_blog( 2 ); update_option( "anybook_settings", array( "slug" => "other" ) ); set_transient( "anybook_status", 1, 600 ); restore_current_blog();'
wp plugin deactivate anybook
wp plugin uninstall anybook --skip-delete
left=$(wp eval 'echo (int) (bool) get_option( "anybook_settings" ); switch_to_blog( 2 ); echo (int) (bool) get_option( "anybook_settings" ), (int) (bool) get_transient( "anybook_status" ); restore_current_blog();')
[ "$left" = "000" ] || fail "uninstall left data behind: $left"
[ "$(wp post get "$id" --field=post_status)" = "publish" ] || fail "uninstall touched the booking page"
pass "uninstall cleared both sites and kept the booking page"

echo "== smoke passed"
