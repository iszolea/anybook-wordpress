<?php
/**
 * The plugin's settings page: Connect, the business-code fallback, status, booking page and disconnect.
 *
 * @package Anybook
 */

namespace Anybook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Anybook page in WordPress admin (contracts.md: admin.php?page=anybook and admin-post.php?action=anybook_*).
 *
 * It is a top-level page on purpose: Anybook accepts a return address only if it is
 * .../wp-admin/admin.php?page=anybook (ADR-3). Every action needs manage_options and a nonce; the connect return
 * needs the single-use state instead, which Anybook carries back.
 */
class Admin {

	const PAGE = 'anybook';

	/** The handle of the settings page's style and script. */
	const ASSET_HANDLE = 'anybook-admin';

	/** Who may use the page and its actions. */
	const CAPABILITY = 'manage_options';

	/**
	 * Who may change the booking site address (review S-M1): only someone WordPress already trusts to post scripts.
	 * On a network that is the super admin; site admins have manage_options but not this.
	 */
	const SITE_CAPABILITY = 'unfiltered_html';

	/** The admin-post actions and their handlers. */
	const ACTIONS = array(
		'anybook_connect'       => 'handle_connect',
		'anybook_save_code'     => 'handle_save_code',
		'anybook_save_settings' => 'handle_save_settings',
		'anybook_disconnect'    => 'handle_disconnect',
		'anybook_refresh'       => 'handle_refresh',
		'anybook_publish_page'  => 'handle_publish_page',
	);

	/** Hooks the page and its actions. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_page_assets' ) );
		foreach ( self::ACTIONS as $action => $method ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( ANYBOOK_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'connect_notice' ) );
		add_action( 'admin_init', array( Plugin::class, 'privacy_policy_content' ) );
	}

	/** Adds the Anybook menu item. */
	public static function menu() {
		$hook = add_menu_page(
			__( 'Anybook Online Booking', 'anybook' ),
			__( 'Anybook', 'anybook' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-calendar-alt',
			58
		);
		if ( $hook ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'maybe_handle_return' ) );
		}
	}

	/**
	 * The settings page's small style and its "are you sure" for Disconnect, on this page only.
	 *
	 * @param string $hook The admin page being loaded.
	 */
	public static function enqueue_page_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_register_style( self::ASSET_HANDLE, false, array(), ANYBOOK_VERSION );
		wp_enqueue_style( self::ASSET_HANDLE );
		wp_add_inline_style( self::ASSET_HANDLE, '.anybook-inline-form{display:inline}.anybook-status .anybook-ok{color:#00a32a}.anybook-status .anybook-warn{color:#d63638}' );
		wp_register_script( self::ASSET_HANDLE, false, array(), ANYBOOK_VERSION, true );
		wp_enqueue_script( self::ASSET_HANDLE );
		wp_add_inline_script( self::ASSET_HANDLE, 'document.addEventListener("submit",function(e){var m=e.target.getAttribute("data-anybook-confirm");if(m&&!window.confirm(m)){e.preventDefault();}});' );
	}

	/**
	 * A "Settings" link beside the plugin on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 */
	public static function action_links( $links ) {
		$links   = is_array( $links ) ? $links : array();
		$links[] = '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'anybook' ) . '</a>';
		return $links;
	}

	/**
	 * After activation, on the Plugins screen only: a pointer to Connect, until the site is connected. The plugin's
	 * own page has Connect itself, and no other admin screen shows this.
	 */
	public static function connect_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) || Settings::is_connected() ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Anybook Online Booking is ready. Connect it to your Anybook business to show your booking widget on this website.', 'anybook' ),
			esc_url( self::page_url() ),
			esc_html__( 'Connect to Anybook', 'anybook' )
		);
	}

	/**
	 * This page's address, with an optional notice code.
	 *
	 * @param string $notice Notice code, or ''.
	 */
	public static function page_url( $notice = '' ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		return '' === $notice ? $url : add_query_arg( 'anybook_notice', rawurlencode( $notice ), $url );
	}

	/**
	 * The browser coming back from Anybook: handle it once, then reload the page without the return values so a
	 * refresh cannot replay it.
	 */
	public static function maybe_handle_return() {
		// The single-use state Anybook carries back does the nonce's job here; Connection checks it.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['state'] ) || ! ( isset( $_GET['anybook_slug'] ) || isset( $_GET['anybook_error'] ) ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to connect this site to Anybook.', 'anybook' ), '', array( 'response' => 403 ) );
		}
		$query = array();
		foreach ( array( 'state', 'anybook_slug', 'anybook_name', 'anybook_error' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) {
				$query[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$notice = Connection::handle_return( $query, get_current_user_id() );
		self::redirect( self::page_url( $notice ) );
	}

	/**
	 * Connect to Anybook: draft the booking page if asked, then send the browser to Anybook. The page is published
	 * only when the Owner agrees there (review Q1).
	 */
	public static function handle_connect() {
		self::check( 'anybook_connect' );
		if ( Settings::admin_origin() !== Settings::site_origin() ) {
			// Anybook would allow the admin's address, where the widget never shows (review S-I1).
			self::redirect( self::page_url( 'origin-mismatch' ) );
		}
		$guest_page = '';
		$page_made  = false;
		if ( ! empty( $_POST['anybook_make_page'] ) && Booking_Page::is_held_back() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
			// The admin made the page private, pending or scheduled on purpose: it is neither published nor offered.
			$guest_page = '';
		} elseif ( ! empty( $_POST['anybook_make_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
			if ( Booking_Page::is_published() ) {
				$guest_page = (string) get_permalink( Booking_Page::current_id() );
			} else {
				$page_id = Booking_Page::ensure_draft();
				if ( is_wp_error( $page_id ) ) {
					self::redirect( self::page_url( 'page-failed' ) );
				}
				$guest_page = Booking_Page::future_permalink( $page_id );
				$page_made  = true;
			}
		}
		$url = Connection::start( get_current_user_id(), $guest_page, $page_made );

		// wp_safe_redirect, with the booking site allowed for this one redirect only.
		$host  = wp_parse_url( Settings::site(), PHP_URL_HOST );
		$allow = function ( $hosts ) use ( $host ) {
			$hosts[] = $host;
			return $hosts;
		};
		add_filter( 'allowed_redirect_hosts', $allow );
		self::redirect( $url );
	}

	/** The business-code fallback. */
	public static function handle_save_code() {
		self::check( 'anybook_save_code' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
		$code = isset( $_POST['anybook_code'] ) ? sanitize_text_field( wp_unslash( $_POST['anybook_code'] ) ) : '';
		self::redirect( self::page_url( Connection::save_code( $code ) ) );
	}

	/** Default colors, and the booking site address under Advanced. */
	public static function handle_save_settings() {
		self::check( 'anybook_save_settings' );
		$notice  = 'saved';
		$changes = array();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in check().
		foreach ( array(
			'accent' => 'anybook_accent',
			'text'   => 'anybook_text',
		) as $key => $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$raw   = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			$clean = Settings::clean_colour( $raw );
			if ( '' !== $raw && '' === $clean ) {
				$notice = 'bad-colour'; // The color saved before stays.
				continue;
			}
			$changes[ $key ] = $clean;
		}
		$site = isset( $_POST['anybook_site'] ) ? sanitize_text_field( wp_unslash( $_POST['anybook_site'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' !== $site && Settings::clean_site( $site ) !== Settings::site() ) {
			$clean_site = Settings::form_site( $site );
			if ( ! self::may_change_site() ) {
				$notice = 'site-not-allowed';
			} elseif ( '' === $clean_site ) {
				$notice = 'bad-site';
			} else {
				$changes['site'] = $clean_site;
				Settings::clear_cache();
			}
		}
		if ( $changes ) {
			Settings::update( $changes );
		}
		self::redirect( self::page_url( $notice ) );
	}

	/**
	 * Whether this user may change the booking site on the settings page: never when wp-config.php sets it, and
	 * only with unfiltered_html (review S-M1).
	 */
	public static function may_change_site() {
		return '' === Settings::site_from_constant() && current_user_can( self::SITE_CAPABILITY );
	}

	/** Disconnect from the business. */
	public static function handle_disconnect() {
		self::check( 'anybook_disconnect' );
		Connection::disconnect();
		self::redirect( self::page_url( 'disconnected' ) );
	}

	/**
	 * Publishes the booking page the plugin made, for when Anybook already uses it as the guest page but the return
	 * from Connect never arrived (the tab was closed after Allow, or the 15 minutes ran out).
	 */
	public static function handle_publish_page() {
		self::check( 'anybook_publish_page' );
		Booking_Page::publish( Booking_Page::current_id() );
		self::redirect( self::page_url( 'page-published' ) );
	}

	/** Ask Anybook again for the status, at most once every 30 seconds per person (reviews S-L2, Q15). */
	public static function handle_refresh() {
		self::check( 'anybook_refresh' );
		if ( ! Client::may_refresh( get_current_user_id() ) ) {
			self::redirect( self::page_url( 'checked-recently' ) );
		}
		Settings::clear_cache();
		self::redirect( self::page_url() );
	}

	/**
	 * Stops unless the user may act and the form's nonce is good.
	 *
	 * @param string $action Nonce action.
	 */
	private static function check( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the Anybook settings.', 'anybook' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirects and stops.
	 *
	 * @param string $url Where to.
	 */
	private static function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * The notice for a code, as [type, message], or null.
	 *
	 * @param string $code Notice code from the address.
	 */
	public static function notice( $code ) {
		$fallback = __( 'You can also enter your business code below: Anybook shows it to every staff member under Settings, Online booking.', 'anybook' );
		$notices  = array(
			'connected'          => array( 'success', __( 'Connected to Anybook. Next, add the Anybook booking block to a page, or use your Book online page if you asked for one: it is now published.', 'anybook' ) ),
			'connected-pending'  => array( 'warning', __( 'Connected. Anybook has not approved your business yet, so the booking widget will not show until it does.', 'anybook' ) ),
			'disconnected'       => array( 'success', __( 'Disconnected. The booking widget no longer shows on this website, and your Book online page shows visitors nothing until you connect again. To stop this website showing your widget for good, remove it from allowed websites in Anybook too.', 'anybook' ) ),
			'saved'              => array( 'success', __( 'Settings saved.', 'anybook' ) ),
			'error-cancelled'    => array( 'warning', __( 'Connecting was cancelled on Anybook. Nothing was changed there, and a booking page made for this connect stays a draft.', 'anybook' ) ),
			'error-not-owner'    => array( 'warning', __( 'Only your business\'s Owner can connect a website on Anybook. Ask the Owner to click Connect here, or enter your business code below.', 'anybook' ) ),
			'error-not-approved' => array( 'warning', __( 'Your business is not approved on Anybook yet. Connect again once Anybook has approved it.', 'anybook' ) . ' ' . $fallback ),
			'error-other'        => array( 'error', __( 'Anybook could not connect this site.', 'anybook' ) . ' ' . $fallback ),
			'bad-state'          => array( 'error', __( 'That connect link has expired or was already used. Please click Connect to Anybook again.', 'anybook' ) ),
			'bad-return'         => array( 'error', __( 'Anybook sent back something the plugin could not read. Please click Connect to Anybook again.', 'anybook' ) ),
			'bad-code'           => array( 'error', __( 'That is not a business code. Codes are letters, numbers and dashes, like bribie.', 'anybook' ) ),
			'unknown-business'   => array( 'error', __( 'Anybook has no approved business with that code. Check the code, or try again once Anybook has approved your business.', 'anybook' ) ),
			'unreachable'        => array( 'error', __( 'Anybook could not be reached. Please try again in a minute.', 'anybook' ) ),
			'bad-colour'         => array( 'error', __( 'A color was not saved: colors are written like #0b6e4f. The color saved before is kept.', 'anybook' ) ),
			'bad-site'           => array( 'error', __( 'The booking site must be https://site.anybookapp.com. For any other address, set ANYBOOK_SITE in wp-config.php. Other settings were saved.', 'anybook' ) ),
			'site-not-allowed'   => array( 'error', __( 'Only an administrator who may add scripts to this site can change the booking site address. Other settings were saved.', 'anybook' ) ),
			'origin-mismatch'    => array( 'error', __( 'Your WordPress admin and your website have different addresses, so Connect would allow the wrong one. Enter your business code below instead.', 'anybook' ) ),
			'checked-recently'   => array( 'info', __( 'Anybook was asked less than 30 seconds ago. The status below is its answer.', 'anybook' ) ),
			'page-published'     => array( 'success', __( 'Your booking page is published.', 'anybook' ) ),
			'page-failed'        => array( 'error', __( 'The booking page could not be made. Untick "Make a booking page for me" to connect without it.', 'anybook' ) ),
		);
		return isset( $notices[ $code ] ) ? $notices[ $code ] : null;
	}

	/** Prints the settings page. */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$settings = Settings::all();
		// Only a code chosen from the fixed list above is shown; nothing from the address is printed.
		$code   = isset( $_GET['anybook_notice'] ) ? sanitize_key( wp_unslash( $_GET['anybook_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = self::notice( $code );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Anybook Online Booking', 'anybook' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
			<?php endif; ?>
			<?php if ( Settings::shares_origin() ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php
					/* translators: %s: this website's address, like https://example.com. */
					echo esc_html( sprintf( __( 'Other websites share this website\'s address (%s), for example the other sites of a network that uses folders. Whatever your business allows for this address, it allows for them too: they could show your booking widget and read what guests type in it. Give your booking website its own domain or subdomain before you connect.', 'anybook' ), Settings::site_origin() ) );
					?>
				</p></div>
			<?php endif; ?>

			<?php
			if ( Settings::is_connected() ) {
				self::render_connected( $settings );
			} else {
				self::render_connect();
			}
			self::render_settings( $settings );
			?>
		</div>
		<?php
	}

	/** Connect and the business-code fallback. */
	private static function render_connect() {
		?>
		<h2><?php esc_html_e( 'Connect to Anybook', 'anybook' ); ?></h2>
		<?php if ( Settings::admin_origin() !== Settings::site_origin() ) : ?>
			<p>
				<?php
				/* translators: 1: the WordPress admin's address, 2: the website's address. */
				echo esc_html( sprintf( __( 'Your WordPress admin (%1$s) and your website (%2$s) have different addresses. Connect would allow the admin\'s address on Anybook, where your widget never shows, so use your business code below. Then, in Anybook, add %2$s to allowed websites.', 'anybook' ), Settings::admin_origin(), Settings::site_origin() ) );
				?>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Anybook opens in this window. Sign in as your business\'s Owner and click Allow. Anybook then adds this website to your allowed websites (Anybook shows your booking widget only on websites on that list) and, if you tick it there, makes the booking page below your guest page (the page where guests\' email links open). No keys or passwords are copied.', 'anybook' ); ?></p>
			<?php self::render_connect_form( __( 'Connect to Anybook', 'anybook' ) ); ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Or enter your business code', 'anybook' ); ?></h2>
		<p><?php esc_html_e( 'For staff who are not the Owner. This only connects the plugin: add this website to allowed websites and set your guest page in Anybook yourself (Settings, Online booking).', 'anybook' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="anybook_save_code" />
			<?php wp_nonce_field( 'anybook_save_code' ); ?>
			<label for="anybook_code" class="screen-reader-text"><?php esc_html_e( 'Business code', 'anybook' ); ?></label>
			<input type="text" id="anybook_code" name="anybook_code" class="regular-text" placeholder="<?php esc_attr_e( 'for example bribie', 'anybook' ); ?>" pattern="[A-Za-z0-9_\-]{1,64}" required />
			<?php submit_button( __( 'Save code', 'anybook' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * The Connect button with the booking-page choice, and what it sends.
	 *
	 * @param string $label Button text.
	 */
	private static function render_connect_form( $label ) {
		$page_problem = Booking_Page::problem();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="anybook_connect" />
			<?php wp_nonce_field( 'anybook_connect' ); ?>
			<p>
				<label>
					<input type="checkbox" name="anybook_make_page" value="1" checked="checked" />
					<?php
					echo esc_html(
						Booking_Page::current_id() && '' === $page_problem
							? __( 'Use my booking page as the guest page', 'anybook' )
							: __( 'Make a booking page for me ("Book online") and use it as the guest page', 'anybook' )
					);
					?>
				</label>
			</p>
			<p class="description"><?php esc_html_e( 'A new booking page is kept as a draft and published only when the Owner clicks Allow on Anybook.', 'anybook' ); ?></p>
			<?php submit_button( $label, 'primary', 'submit', false ); ?>
		</form>
		<p class="description">
			<?php
			/* translators: %s: booking site address. */
			echo esc_html( sprintf( __( 'Connecting sends this website\'s name and address to Anybook (%s).', 'anybook' ), Settings::site() ) );
			?>
		</p>

		<?php
	}

	/**
	 * The status panel, booking page and disconnect.
	 *
	 * @param array $settings Stored settings.
	 */
	private static function render_connected( array $settings ) {
		$status = Client::site_status( $settings['slug'] );
		$origin = Settings::site_origin();
		?>
		<h2><?php esc_html_e( 'Status', 'anybook' ); ?></h2>
		<ul class="anybook-status">
			<?php
			/* translators: 1: business name, 2: business code. */
			self::line( true, sprintf( __( 'Connected to %1$s (business code %2$s).', 'anybook' ), $settings['name'], $settings['slug'] ) );

			if ( is_wp_error( $status ) ) {
				$why = $status->get_error_message();
				if ( 'anybook_unreachable' === $status->get_error_code() ) {
					$why .= ' ' . __( 'Only this page is affected: your visitors\' booking widget loads straight from Anybook and does not depend on this check.', 'anybook' );
				}
				self::line( false, $why );
			} else {
				self::line(
					$status['approved'],
					$status['approved'] ? __( 'Your business is approved on Anybook.', 'anybook' ) : __( 'Your business is waiting for Anybook\'s approval.', 'anybook' )
				);
				if ( ! empty( $status['credit']['required'] ) ) {
					self::line(
						true,
						__( 'Your Anybook plan asks for a small "Booking by Anybook" link under your booking widget. The plugin adds it for you; a paid plan removes it.', 'anybook' )
					);
				}
				self::line(
					$status['onlineBooking'],
					$status['onlineBooking'] ? __( 'Online booking is on.', 'anybook' ) : __( 'Online booking is off on Anybook (Settings, Online booking).', 'anybook' )
				);
				self::line(
					$status['originAllowed'],
					$status['originAllowed']
						/* translators: %s: this website's address. */
						? sprintf( __( 'This website (%s) is on your allowed websites, so it can show your booking widget.', 'anybook' ), $origin )
						/* translators: %s: this website's address. */
						: sprintf( __( 'This website (%s) is not on your allowed websites in Anybook, so the widget will not load here. Connect again as the Owner, or add it in Anybook (Settings, Online booking).', 'anybook' ), $origin )
				);
				$guest_messages = array(
					'this-site' => __( 'Your guest page (where guests\' email links open) is on this website.', 'anybook' ),
					'elsewhere' => __( 'Your guest page (where guests\' email links open) is on another website.', 'anybook' ),
					'none'      => __( 'You have no guest page yet. Guests\' email links (to see, change or pay for a booking) need one: see Set your guest page below.', 'anybook' ),
				);
				self::line( 'this-site' === $status['guestPage'], $guest_messages[ $status['guestPage'] ] );
			}

			$page_messages = array(
				'missing'     => __( 'The booking page the plugin made has been deleted.', 'anybook' ),
				'unpublished' => __( 'The booking page is not published.', 'anybook' ),
				'no-widget'   => __( 'The booking page no longer holds the Anybook widget.', 'anybook' ),
			);
			$problem       = Booking_Page::problem();
			if ( '' !== $problem ) {
				self::line( false, $page_messages[ $problem ] );
			}
			?>
		</ul>
		<?php if ( 'unpublished' === $problem && 'draft' === get_post_status( Booking_Page::current_id() ) && ! is_wp_error( $status ) && 'this-site' === $status['guestPage'] ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="anybook_publish_page" />
				<?php wp_nonce_field( 'anybook_publish_page' ); ?>
				<p><?php esc_html_e( 'Anybook already uses this website for your guests\' email links, but the booking page is still a draft.', 'anybook' ); ?>
				<?php submit_button( __( 'Publish the booking page', 'anybook' ), 'primary', 'submit', false ); ?></p>
			</form>
		<?php endif; ?>

		<?php if ( ! is_wp_error( $status ) && $status['cspSources'] ) : ?>
			<details>
				<summary><?php esc_html_e( 'If your site sends a content security policy', 'anybook' ); ?></summary>
				<p><?php esc_html_e( 'A content security policy is a list, sent by some websites, of the places a page may load code from. Most sites have none; if yours has one, give these lines to whoever manages your site\'s security headers. The plugin sets no policy itself.', 'anybook' ); ?></p>
				<?php
				$csp_text = '';
				foreach ( $status['cspSources'] as $csp_line ) {
					$csp_text .= $csp_line['directive'] . ' ' . implode( ' ', $csp_line['sources'] ) . "\n";
				}
				?>
				<pre><?php echo esc_html( $csp_text ); ?></pre>
			</details>
		<?php endif; ?>

		<?php
		$optimisers = Assets::active_optimisers();
		if ( $optimisers ) :
			?>
			<p>
				<?php
				/* translators: %s: names of speed plugins. */
				echo esc_html( sprintf( __( 'Speed plugins found: %s. The plugin asks them to leave the booking widget\'s script alone; if the widget does not appear, exclude booking-widget.js from delaying and combining in their settings.', 'anybook' ), implode( ', ', $optimisers ) ) );
				?>
			</p>
		<?php endif; ?>
		<p><?php esc_html_e( 'Do not run session recording (replay) in your analytics on the booking page: it would record what guests type.', 'anybook' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="anybook-inline-form">
			<input type="hidden" name="action" value="anybook_refresh" />
			<?php wp_nonce_field( 'anybook_refresh' ); ?>
			<?php submit_button( __( 'Check again', 'anybook' ), 'secondary', 'submit', false ); ?>
		</form>
		<a class="button" href="<?php echo esc_url( Settings::site() . '/settings/online-booking' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Anybook online booking settings', 'anybook' ); ?></a>

		<?php
		// Connect sets the guest page only if the Owner ticks it on Anybook (ADR-19), so it may still be missing.
		$needs_guest_page = ! is_wp_error( $status ) && 'this-site' !== $status['guestPage'];
		?>
		<h2><?php echo esc_html( $needs_guest_page ? __( 'Set your guest page', 'anybook' ) : __( 'Connect again', 'anybook' ) ); ?></h2>
		<?php if ( Settings::admin_origin() !== Settings::site_origin() ) : ?>
			<p><?php esc_html_e( 'Your WordPress admin and your website have different addresses, so set the guest page and allowed websites in Anybook (Settings, Online booking).', 'anybook' ); ?></p>
		<?php else : ?>
			<p>
				<?php
				echo esc_html(
					$needs_guest_page
						? __( 'To use a page on this website as your guest page, the Owner clicks below and, on Anybook, ticks the guest page choice before clicking Allow.', 'anybook' )
						: __( 'If a line above needs the Owner on Anybook, the Owner can connect again here.', 'anybook' )
				);
				?>
			</p>
			<?php self::render_connect_form( __( 'Connect to Anybook again', 'anybook' ) ); ?>
		<?php endif; ?>

		<?php $page_id = Booking_Page::current_id(); ?>
		<?php if ( $page_id ) : ?>
			<h2><?php esc_html_e( 'Booking page', 'anybook' ); ?></h2>
			<p>
				<a href="<?php echo esc_url( (string) get_permalink( $page_id ) ); ?>"><?php echo esc_html( get_the_title( $page_id ) ); ?></a>
				&middot;
				<a href="<?php echo esc_url( (string) get_edit_post_link( $page_id ) ); ?>"><?php esc_html_e( 'Edit', 'anybook' ); ?></a>
			</p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Disconnect', 'anybook' ); ?></h2>
		<p><?php esc_html_e( 'The widget stops showing on this site. Your booking page stays published but shows visitors nothing until you connect again, and your Anybook account is not changed; remove this website from allowed websites in Anybook as well if you no longer use it.', 'anybook' ); ?></p>
		<?php $confirm = __( 'Disconnect from Anybook? The booking widget stops showing on this website.', 'anybook' ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-anybook-confirm="<?php echo esc_attr( $confirm ); ?>">
			<input type="hidden" name="action" value="anybook_disconnect" />
			<?php wp_nonce_field( 'anybook_disconnect' ); ?>
			<?php submit_button( __( 'Disconnect', 'anybook' ), 'delete', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Default colors (once connected) and the booking site address (for those who may change it).
	 *
	 * @param array $settings Stored settings.
	 */
	private static function render_settings( array $settings ) {
		$connected = Settings::is_connected();
		$constant  = Settings::site_from_constant();
		$site_form = self::may_change_site();
		if ( ! $connected && ! $site_form && '' === $constant ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="anybook_save_settings" />
			<?php wp_nonce_field( 'anybook_save_settings' ); ?>
			<?php if ( $connected ) : ?>
				<h2><?php esc_html_e( 'Widget colors', 'anybook' ); ?></h2>
				<p><?php esc_html_e( 'Used by every booking widget on this site unless its block or shortcode sets its own.', 'anybook' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="anybook_accent"><?php esc_html_e( 'Main color', 'anybook' ); ?></label></th>
						<td><input type="text" id="anybook_accent" name="anybook_accent" value="<?php echo esc_attr( $settings['accent'] ); ?>" placeholder="#0b6e4f" pattern="#([0-9a-fA-F]{3}){1,2}" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="anybook_text"><?php esc_html_e( 'Text color', 'anybook' ); ?></label></th>
						<td><input type="text" id="anybook_text" name="anybook_text" value="<?php echo esc_attr( $settings['text'] ); ?>" placeholder="#1f2933" pattern="#([0-9a-fA-F]{3}){1,2}" /></td>
					</tr>
				</table>
			<?php endif; ?>
			<details>
				<summary><?php esc_html_e( 'Advanced', 'anybook' ); ?></summary>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking site address', 'anybook' ); ?></th>
						<td>
							<?php if ( '' !== $constant ) : ?>
								<code><?php echo esc_html( $constant ); ?></code>
								<p class="description"><?php esc_html_e( 'Set by ANYBOOK_SITE in wp-config.php.', 'anybook' ); ?></p>
							<?php elseif ( $site_form ) : ?>
								<label for="anybook_site" class="screen-reader-text"><?php esc_html_e( 'Booking site address', 'anybook' ); ?></label>
								<input type="url" id="anybook_site" name="anybook_site" class="regular-text" value="<?php echo esc_attr( Settings::site() ); ?>" />
								<p class="description"><?php esc_html_e( 'Leave as https://site.anybookapp.com. Any other address can only be set by ANYBOOK_SITE in wp-config.php.', 'anybook' ); ?></p>
							<?php else : ?>
								<code><?php echo esc_html( Settings::site() ); ?></code>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</details>
			<?php
			if ( $connected || $site_form ) {
				submit_button( __( 'Save settings', 'anybook' ) );
			}
			?>
		</form>
		<?php
	}

	/**
	 * One status line with a tick or a warning.
	 *
	 * @param bool   $ok   Whether this is fine.
	 * @param string $text What to say.
	 */
	private static function line( $ok, $text ) {
		printf(
			'<li><span class="dashicons %1$s" aria-hidden="true"></span> <span class="screen-reader-text">%2$s</span>%3$s</li>',
			esc_attr( $ok ? 'dashicons-yes-alt anybook-ok' : 'dashicons-warning anybook-warn' ),
			esc_html( $ok ? __( 'OK:', 'anybook' ) : __( 'To fix:', 'anybook' ) ),
			esc_html( $text )
		);
	}
}
