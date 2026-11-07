#!/usr/bin/env bash
# A smoke test in a real WordPress (latest official Docker image, MariaDB) with Anybook faked by a must-use plugin:
#   activation makes no call to Anybook; connect return; the booking page stays a draft until the Owner agrees;
#   settings page renders; the booking page's head loads the widget script deferred, unversioned and with the
#   speed-plugin attributes; a page without the widget loads nothing; a classic theme (head on the booking page,
#   footer elsewhere); Elementor-style content; the availability overview (its own script, both scripts on a page with
#   both); Autoptimize and LiteSpeed Cache with their script optimisations on
#   leave the widget's tag alone; Plugin Check (must run, no errors, only allowed warnings); readme "Tested up to"
#   is this WordPress; then multisite: the shared-address warning, and uninstall clears every site and keeps the page.
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
status_of() { docker exec $P-wp curl -s -o /dev/null -w '%{http_code}' "http://localhost$1"; }
fail() { echo "FAIL: $*"; exit 1; }
pass() { echo "ok - $*"; }
# The widget's script tag in a page's head, or ''.
head_tag() { local body; body=$(page "$1"); grep -o '<script[^>]*booking-widget\.js[^>]*>' <<<"${body%%</head>*}" || true; }
# The availability overview's script tag in a page's head, or ''.
overview_tag() { local body; body=$(page "$1"); grep -o '<script[^>]*booking-overview\.js[^>]*>' <<<"${body%%</head>*}" || true; }

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
for shipped in package.json package-lock.json blocks/booking/index.js blocks/booking/edit.js; do
	docker exec $P-wp test -f "/var/www/html/wp-content/plugins/anybook/$shipped" || fail "$shipped (source and build steps) not in the release"
done
docker exec $P-wp test ! -e /var/www/html/wp-content/plugins/anybook/blocks/booking/test || fail "JS tests in the release"

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

# The booking page: a draft, invisible to visitors, while the Owner is on Anybook; a refusal leaves it so; agreeing publishes it.
id=$(wp eval 'echo Anybook\Booking_Page::ensure_draft();')
[ "$(wp post get "$id" --field=post_status)" = "draft" ] || fail "booking page not made as a draft"
[ "$(status_of "/?page_id=$id")" = "404" ] || fail "draft booking page is visible to visitors"
future=$(wp eval "echo Anybook\\Booking_Page::future_permalink( $id );")
[ "$future" = "http://localhost/?page_id=$id" ] || [ "$future" = "http://localhost/book/" ] || fail "future address: $future"
out=$(wp eval '
	$url = Anybook\Connection::start( 1, "x", true );
	parse_str( parse_url( $url, PHP_URL_QUERY ), $q );
	echo Anybook\Connection::handle_return( array( "state" => $q["state"], "anybook_error" => "cancelled" ), 1 );')
[ "$out" = "error-cancelled" ] && [ "$(wp post get "$id" --field=post_status)" = "draft" ] || fail "cancel: $out, $(wp post get "$id" --field=post_status)"
out=$(wp eval '
	$url = Anybook\Connection::start( 1, "x", true );
	parse_str( parse_url( $url, PHP_URL_QUERY ), $q );
	echo Anybook\Connection::handle_return( array( "state" => $q["state"], "anybook_slug" => "bribie" ), 1 );')
[ "$out" = "connected" ] && [ "$(wp post get "$id" --field=post_status)" = "publish" ] || fail "allow: $out, $(wp post get "$id" --field=post_status)"
pass "booking page: a draft until the Owner agrees, then published"

html=$(wp eval 'wp_set_current_user( 1 ); require_once ABSPATH . "wp-admin/includes/template.php"; Anybook\Admin::render_page();')
grep -q 'Connected to Bribie Houseboat Holidays' <<<"$html" || fail "settings page has no connected line"
grep -q 'Set your guest page' <<<"$html" || fail "settings page does not offer to set the guest page"
grep -q 'script-src https://app.anybookapp.com' <<<"$html" || fail "settings page has no CSP lines"
grep -q 'Other websites share' <<<"$html" && fail "shared-address warning on a single site"
pass "settings page renders the status, guest page offer and CSP lines"

body=$(page "/?page_id=$id")
grep -q '<booking-widget business="bribie" api="https://app.anybookapp.com" guest-links></booking-widget>' <<<"$body" \
	|| fail "booking page has no widget element"
tag=$(head_tag "/?page_id=$id")
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
grep -qF "$want" <<<"$(page "/?page_id=$blk")" || fail "block output: $(page "/?page_id=$blk" | grep -o '<booking-widget[^>]*>')"
grep -qF "$want" <<<"$(page "/?page_id=$sc")" || fail "shortcode output: $(page "/?page_id=$sc" | grep -o '<booking-widget[^>]*>')"
pass "block and shortcode give the same element"

wp eval 'echo WP_Block_Type_Registry::get_instance()->is_registered( "anybook/booking" ) ? "yes" : "no";' | grep -qx yes 	|| fail "block not registered"
cat=$(wp eval 'wp_set_current_user( 1 ); $r = rest_do_request( new WP_REST_Request( "GET", "/anybook/v1/catalog" ) ); echo $r->get_status(), " ", wp_json_encode( $r->get_data() );')
[ "$cat" = '200 {"units":[{"id":"u1","code":"belle","name":"The Belle"}],"services":[{"id":"s1","code":"cut","name":"Haircut"}]}' ] 	|| fail "catalog route: $cat"
anon=$(wp eval 'wp_set_current_user( 0 ); echo rest_do_request( new WP_REST_Request( "GET", "/anybook/v1/catalog" ) )->get_status();')
[ "$anon" = "401" ] || fail "catalog route open to visitors: $anon"
pass "block registered; catalogue route answers editors only"

# A contributor's refresh is served from the cache (S-L2).
contrib=$(wp user create helper helper@example.test --role=contributor --porcelain)
before=$(wp option get fake_anybook_calls)
wp eval "wp_set_current_user( $contrib ); \$q = new WP_REST_Request( 'GET', '/anybook/v1/catalog' ); \$q->set_param( 'refresh', true ); rest_do_request( \$q ); rest_do_request( \$q );"
[ "$(wp option get fake_anybook_calls)" = "$before" ] || fail "a contributor's refresh reached Anybook"
pass "catalogue refresh: contributors get the cache"

# The availability overview (PBI 30581): its element, its own script in the head with the same attributes, not the
# widget's; a page with an overview and a widget gets both scripts.
ov=$(wp post create --post_type=page --post_status=publish --post_title=Overview --porcelain \
	--post_content='[anybook show="overview" weeks="8" prices="yes" units="belle,Bad_code" rows="staff"]')
body=$(page "/?page_id=$ov")
grep -qF '<booking-overview business="bribie" api="https://app.anybookapp.com" weeks="8" prices units="belle"></booking-overview>' <<<"$body" \
	|| fail "overview element: $(grep -o '<booking-overview[^>]*>' <<<"$body")"
tag=$(overview_tag "/?page_id=$ov")
for want in 'src="https://app.anybookapp.com/widget/booking-overview.js"' 'defer' 'data-cfasync="false"' 'data-no-optimize="1"' 'nowprocket'; do
	grep -qF "$want" <<<"$tag" || fail "overview script tag lacks $want: $tag"
done
grep -q 'booking-widget\.js' <<<"$body" && fail "overview page loads the widget script"
[ "$(grep -o 'booking-overview\.js' <<<"$body" | wc -l)" = "1" ] || fail "overview script loaded more than once"
both=$(wp post create --post_type=page --post_status=publish --post_title=Both --porcelain \
	--post_content='<!-- wp:anybook/booking {"show":"overview","rows":"people","service":"cut"} /--><!-- wp:anybook/booking {"show":"unit","unit":"belle"} /-->')
body=$(page "/?page_id=$both")
grep -qF '<booking-overview business="bribie" api="https://app.anybookapp.com" rows="people" service="cut"></booking-overview>' <<<"$body" \
	|| fail "overview block element: $(grep -o '<booking-overview[^>]*>' <<<"$body")"
grep -qF '<booking-widget business="bribie" api="https://app.anybookapp.com" unit="belle">' <<<"$body" || fail "widget beside the overview"
[ -n "$(overview_tag "/?page_id=$both")" ] && [ -n "$(head_tag "/?page_id=$both")" ] || fail "page with both: scripts not both in the head"
pass "availability overview: element, its own script in the head (defer, speed-plugin attributes); both scripts beside a widget"

other=$(wp post create --post_type=post --post_status=publish --post_title=Hello --post_content='No widget' --porcelain)
grep -q 'booking-widget\|booking-overview' <<<"$(page "/?p=$other")" && fail "page without the widget loads it"
pass "a page without the widget loads no script"

# Elementor keeps a page's content in _elementor_data (Q9): the script still goes in the head.
el=$(wp post create --post_type=page --post_status=publish --post_title=Elementor --post_content='<p>Built with a page builder</p>' --porcelain)
wp post meta update "$el" _elementor_data '[{"elType":"widget","widgetType":"shortcode","settings":{"shortcode":"[anybook unit=\"belle\"]"}}]'
[ -n "$(head_tag "/?page_id=$el")" ] || fail "Elementor-style page: script not in the head"
pass "Elementor-style content counts as the widget"

# A classic theme (Q6): the booking page made there holds the shortcode block; its head has the script; a list of
# posts that renders the shortcode gets it in the footer.
wp theme install twentytwentyone --activate
wp theme mod set display_excerpt_or_full_post full
classic=$(wp eval 'echo Anybook\Booking_Page::content();')
[ "$classic" = "$(printf '<!-- wp:shortcode -->\n[anybook guest_links="yes"]\n<!-- /wp:shortcode -->')" ] || fail "classic booking page content: $classic"
cpage=$(wp post create --post_type=page --post_status=publish --post_title="Classic booking" --post_content="$classic" --porcelain)
[ -n "$(head_tag "/?page_id=$cpage")" ] || fail "classic theme: script not in the head of the booking page"
grep -qF '<booking-widget business="bribie" api="https://app.anybookapp.com" guest-links>' <<<"$(page "/?page_id=$cpage")" || fail "classic theme: no element"
wp post create --post_type=post --post_status=publish --post_title="Listed" --post_content='[anybook]' >/dev/null
home=$(page "/")
grep -q '<booking-widget' <<<"$home" || fail "classic theme: the post list does not render the shortcode"
[ -z "$(head_tag "/")" ] || fail "classic theme: script in the head of a post list"
grep -q '<script[^>]*booking-widget\.js' <<<"${home#*</head>}" || fail "classic theme: script missing from the footer of a post list"
pass "classic theme (Twenty Twenty-One): head on the booking page, footer on a post list"
cov=$(wp post create --post_type=page --post_status=publish --post_title="Classic overview" --porcelain \
	--post_content="$(printf '<!-- wp:shortcode -->\n[anybook show="overview"]\n<!-- /wp:shortcode -->')")
[ -n "$(overview_tag "/?page_id=$cov")" ] || fail "classic theme: overview script not in the head"

# Speed plugins (Q3): with their script optimisations on, the widget's tag stays as it is, in the head, while the
# plugin is seen optimising the page's other scripts.
docker cp tests/smoke/other-script.php $P-wp:/var/www/html/wp-content/mu-plugins/other-script.php
check_speed() { # name, evidence pattern
	local tag body
	body=$(page "/?page_id=$cpage")
	grep -q "$2" <<<"$body" || fail "$1 did not optimise the page (no $2)"
	tag=$(head_tag "/?page_id=$cpage")
	[ -n "$tag" ] || fail "$1: widget script not in the head"
	for want in 'src="https://app.anybookapp.com/widget/booking-widget.js"' 'defer'; do
		grep -qF "$want" <<<"$tag" || fail "$1 changed the widget tag: $tag"
	done
	grep -qiE 'type="(rocketlazyloadscript|litespeed/javascript|text/plain)"|data-src=' <<<"$tag" && fail "$1 delayed the widget: $tag"
	[ "$(grep -o 'booking-widget\.js' <<<"$body" | wc -l)" = "1" ] || fail "$1 copied the widget script"
	grep -q '<booking-widget business="bribie"' <<<"$(page "/?page_id=$cpage")" || fail "$1 lost the element"
	# The overview's script, likewise.
	body=$(page "/?page_id=$cov")
	tag=$(overview_tag "/?page_id=$cov")
	[ -n "$tag" ] || fail "$1: overview script not in the head"
	for want in 'src="https://app.anybookapp.com/widget/booking-overview.js"' 'defer'; do
		grep -qF "$want" <<<"$tag" || fail "$1 changed the overview tag: $tag"
	done
	grep -qiE 'type="(rocketlazyloadscript|litespeed/javascript|text/plain)"|data-src=' <<<"$tag" && fail "$1 delayed the overview: $tag"
	[ "$(grep -o 'booking-overview\.js' <<<"$body" | wc -l)" = "1" ] || fail "$1 copied the overview script"
	grep -q '<booking-overview business="bribie"' <<<"$body" || fail "$1 lost the overview element"
	pass "$1 ($(wp plugin get "$1" --field=version)) with script optimisations on leaves the widget alone"
}
wp plugin install autoptimize --activate
for o in autoptimize_js=on autoptimize_js_aggregate=on autoptimize_js_defer_not_aggregate=on autoptimize_js_defer_inline=on; do
	wp option update "${o%%=*}" "${o#*=}"
done
check_speed autoptimize 'wp-content/cache/autoptimize/'
wp plugin deactivate autoptimize
wp plugin install litespeed-cache --activate
for o in optm-js_min=1 optm-js_comb=1 optm-js_comb_ext_inl=1 optm-js_defer=2; do
	wp litespeed-option set "${o%%=*}" "${o#*=}" >/dev/null
done
check_speed litespeed-cache 'litespeed/javascript\|wp-content/litespeed/js/'
wp plugin deactivate litespeed-cache
wp theme activate twentytwentyfive

# Plugin Check (from wordpress.org) on the shipped tree (Q5): it must run, find no errors, and warn only about
# what is listed here (codes, as a pattern; none so far). A canary plugin with a known error shows the check and
# this script do see errors.
ALLOWED_WARNINGS='^$'
pcheck() { docker run --rm -i --network $NET -v $P-html:/var/www/html --user 33:33 "${DBENV[@]}" wordpress:cli wp plugin check "$1" --format=csv --fields=file,line,type,code,message 2>&1 || true; }
wp plugin install plugin-check --activate
docker exec $P-wp sh -c 'mkdir -p /var/www/html/wp-content/plugins/canary && printf "%s\n" "<?php" "/* Plugin Name: Canary */" "echo \$_GET[\"x\"];" > /var/www/html/wp-content/plugins/canary/canary.php && chown -R www-data /var/www/html/wp-content/plugins/canary'
grep -q ',ERROR,' <<<"$(pcheck canary)" || fail "Plugin Check missed the canary's error"
docker exec $P-wp rm -rf /var/www/html/wp-content/plugins/canary
pc=$(pcheck anybook)
echo "$pc" | sed 's/^/  plugin-check: /'
grep -qE 'Success: Checks complete|^file,line,type' <<<"$pc" || fail "Plugin Check did not run"
grep -q ',ERROR,' <<<"$pc" && fail "Plugin Check found errors"
warnings=$(grep ',WARNING,' <<<"$pc" | cut -d, -f4 | sort -u | grep -vE "$ALLOWED_WARNINGS" || true)
[ -z "$warnings" ] || fail "Plugin Check warnings not on the allowed list: $warnings"
pass "Plugin Check ran: no errors, no warnings beyond the allowed list"

wpv=$(wp core version | cut -d. -f1,2)
[ "$(docker exec $P-wp grep -m1 '^Tested up to:' /var/www/html/wp-content/plugins/anybook/readme.txt)" = "Tested up to: $wpv" ] \
	|| fail "readme Tested up to is not this WordPress ($wpv)"
pass "readme Tested up to: $wpv"

# Multisite: a subdirectory network warns about the shared address; uninstall clears every site and keeps the page.
wp core multisite-convert --title="Smoke network"
wp site create --slug=two
html=$(wp eval 'wp_set_current_user( 1 ); require_once ABSPATH . "wp-admin/includes/template.php"; Anybook\Admin::render_page();')
grep -q 'Other websites share' <<<"$html" || fail "no shared-address warning on a subdirectory network"
pass "subdirectory network: the settings page warns about the shared address"
wp eval 'switch_to_blog( 2 ); update_option( "anybook_settings", array( "slug" => "other" ) ); update_option( "anybook_connected", "1" ); set_transient( "anybook_status", 1, 600 ); set_transient( "anybook_refresh_1", 1, 30 ); restore_current_blog();'
wp plugin deactivate anybook
wp plugin uninstall anybook --skip-delete
left=$(wp eval 'echo (int) (bool) get_option( "anybook_settings" ), (int) (bool) get_option( "anybook_connected" ); switch_to_blog( 2 ); echo (int) (bool) get_option( "anybook_settings" ), (int) (bool) get_option( "anybook_connected" ), (int) (bool) get_transient( "anybook_status" ), (int) (bool) get_transient( "anybook_refresh_1" ); restore_current_blog();')
[ "$left" = "000000" ] || fail "uninstall left data behind: $left"
[ "$(wp post get "$id" --field=post_status)" = "publish" ] || fail "uninstall touched the booking page"
pass "uninstall cleared both sites and kept the booking page"

echo "== smoke passed"
