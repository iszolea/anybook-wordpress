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

	/** Who may use the page and its actions. */
	const CAPABILITY = 'manage_options';

	/** The admin-post actions and their handlers. */
	const ACTIONS = array(
		'anybook_connect'       => 'handle_connect',
		'anybook_save_code'     => 'handle_save_code',
		'anybook_save_settings' => 'handle_save_settings',
		'anybook_disconnect'    => 'handle_disconnect',
		'anybook_refresh'       => 'handle_refresh',
	);

	/** Hooks the page and its actions. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		foreach ( self::ACTIONS as $action => $method ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( ANYBOOK_FILE ), array( __CLASS__, 'action_links' ) );
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

	/** Connect to Anybook: make the booking page if asked, then send the browser to Anybook. */
	public static function handle_connect() {
		self::check( 'anybook_connect' );
		$guest_page = '';
		$page_made  = false;
		if ( ! empty( $_POST['anybook_make_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
			$existing   = Booking_Page::current_id();
			$was_public = $existing && 'publish' === get_post_status( $existing );
			$page_id    = Booking_Page::ensure_published();
			if ( is_wp_error( $page_id ) ) {
				self::redirect( self::page_url( 'page-failed' ) );
			}
			$guest_page = (string) get_permalink( $page_id );
			$page_made  = ! $was_public;
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

	/** Default colours, and the booking site address under Advanced. */
	public static function handle_save_settings() {
		self::check( 'anybook_save_settings' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in check().
		$accent = isset( $_POST['anybook_accent'] ) ? sanitize_text_field( wp_unslash( $_POST['anybook_accent'] ) ) : '';
		$text   = isset( $_POST['anybook_text'] ) ? sanitize_text_field( wp_unslash( $_POST['anybook_text'] ) ) : '';
		$site   = isset( $_POST['anybook_site'] ) ? sanitize_text_field( wp_unslash( $_POST['anybook_site'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$changes = array(
			'accent' => Settings::clean_colour( $accent ),
			'text'   => Settings::clean_colour( $text ),
		);
		$notice  = 'saved';
		if ( '' !== $site ) {
			$clean_site = Settings::clean_site( $site );
			if ( '' === $clean_site ) {
				$notice = 'bad-site';
			} elseif ( Settings::site() !== $clean_site ) {
				$changes['site'] = $clean_site;
				Settings::clear_cache();
			}
		}
		Settings::update( $changes );
		self::redirect( self::page_url( $notice ) );
	}

	/** Disconnect from the business. */
	public static function handle_disconnect() {
		self::check( 'anybook_disconnect' );
		Connection::disconnect();
		self::redirect( self::page_url( 'disconnected' ) );
	}

	/** Ask Anybook again for the status. */
	public static function handle_refresh() {
		self::check( 'anybook_refresh' );
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
			'connected'          => array( 'success', __( 'Connected to Anybook.', 'anybook' ) ),
			'connected-pending'  => array( 'warning', __( 'Connected. Anybook has not approved your business yet, so the booking widget will not show until it does.', 'anybook' ) ),
			'disconnected'       => array( 'success', __( 'Disconnected. To stop this website showing your booking widget, remove it from allowed websites in Anybook too.', 'anybook' ) ),
			'saved'              => array( 'success', __( 'Settings saved.', 'anybook' ) ),
			'error-cancelled'    => array( 'warning', __( 'Connecting was cancelled on Anybook. Nothing was changed there.', 'anybook' ) ),
			'error-not-owner'    => array( 'warning', __( 'Only your business\'s Owner can connect a website on Anybook. Ask the Owner to click Connect here, or enter your business code below.', 'anybook' ) ),
			'error-not-approved' => array( 'warning', __( 'Your business is not approved on Anybook yet. Connect again once Anybook has approved it.', 'anybook' ) . ' ' . $fallback ),
			'error-other'        => array( 'error', __( 'Anybook could not connect this site.', 'anybook' ) . ' ' . $fallback ),
			'bad-state'          => array( 'error', __( 'That connect link has expired or was already used. Please click Connect to Anybook again.', 'anybook' ) ),
			'bad-return'         => array( 'error', __( 'Anybook sent back something the plugin could not read. Please click Connect to Anybook again.', 'anybook' ) ),
			'bad-code'           => array( 'error', __( 'That is not a business code. Codes are letters, numbers and dashes, like bribie.', 'anybook' ) ),
			'unknown-business'   => array( 'error', __( 'Anybook has no approved business with that code. Check the code, or try again once Anybook has approved your business.', 'anybook' ) ),
			'unreachable'        => array( 'error', __( 'Anybook could not be reached. Please try again in a minute.', 'anybook' ) ),
			'bad-site'           => array( 'error', __( 'The booking site address must be https, with no path, like https://app.anybookapp.com. Other settings were saved.', 'anybook' ) ),
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
		<p><?php esc_html_e( 'Anybook opens in this window. Sign in as your business\'s Owner and click Allow: Anybook lets this website show your booking widget and, if you like, uses the booking page below for your guests\' email links. No keys or passwords are copied.', 'anybook' ); ?></p>
		<?php
		self::render_connect_form( __( 'Connect to Anybook', 'anybook' ) );
		?>

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
				self::line( false, $status->get_error_message() );
			} else {
				self::line(
					$status['approved'],
					$status['approved'] ? __( 'Your business is approved on Anybook.', 'anybook' ) : __( 'Your business is waiting for Anybook\'s approval.', 'anybook' )
				);
				self::line(
					$status['onlineBooking'],
					$status['onlineBooking'] ? __( 'Online booking is on.', 'anybook' ) : __( 'Online booking is off on Anybook (Settings, Online booking).', 'anybook' )
				);
				self::line(
					$status['originAllowed'],
					$status['originAllowed']
						/* translators: %s: this website's address. */
						? sprintf( __( 'This website (%s) is allowed to show your booking widget.', 'anybook' ), $origin )
						/* translators: %s: this website's address. */
						: sprintf( __( 'This website (%s) is not on your allowed websites in Anybook: the widget will not load here. Connect again as the Owner, or add it in Anybook (Settings, Online booking).', 'anybook' ), $origin )
				);
				$guest_messages = array(
					'this-site' => __( 'Your guest page is on this website.', 'anybook' ),
					'elsewhere' => __( 'Your guest page is on another website. Guests\' email links open there.', 'anybook' ),
					'none'      => __( 'You have no guest page yet: guests\' email links need one (see Set your guest page below).', 'anybook' ),
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

		<?php if ( ! is_wp_error( $status ) && $status['cspSources'] ) : ?>
			<details>
				<summary><?php esc_html_e( 'If your site sends a content security policy', 'anybook' ); ?></summary>
				<p><?php esc_html_e( 'Give these lines to whoever manages your site\'s security headers. The plugin sets no policy itself.', 'anybook' ); ?></p>
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

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
			<input type="hidden" name="action" value="anybook_refresh" />
			<?php wp_nonce_field( 'anybook_refresh' ); ?>
			<?php submit_button( __( 'Check again', 'anybook' ), 'secondary', 'submit', false ); ?>
		</form>
		<a class="button" href="<?php echo esc_url( Settings::site() . '/settings/online-booking' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Anybook online booking settings', 'anybook' ); ?></a>

		<?php
		// Connect sets the guest page only if the Owner ticks it on Anybook (ADR-19), so it may still be missing.
		if ( ! is_wp_error( $status ) && 'this-site' !== $status['guestPage'] ) :
			?>
			<h2><?php esc_html_e( 'Set your guest page', 'anybook' ); ?></h2>
			<p><?php esc_html_e( 'Guests\' email links (to see, change or pay for a booking) open on your guest page. To use a page on this website, the Owner clicks below and, on Anybook, ticks the guest page choice before clicking Allow.', 'anybook' ); ?></p>
			<?php self::render_connect_form( __( 'Set your guest page', 'anybook' ) ); ?>
		<?php else : ?>
			<h2><?php esc_html_e( 'Connect again', 'anybook' ); ?></h2>
			<p><?php esc_html_e( 'If a line above needs the Owner on Anybook, the Owner can connect again here.', 'anybook' ); ?></p>
			<?php self::render_connect_form( __( 'Connect again', 'anybook' ) ); ?>
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
		<p><?php esc_html_e( 'The widget stops showing on this site. Your booking page and your Anybook account are not changed; remove this website from allowed websites in Anybook as well if you no longer use it.', 'anybook' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="anybook_disconnect" />
			<?php wp_nonce_field( 'anybook_disconnect' ); ?>
			<?php submit_button( __( 'Disconnect', 'anybook' ), 'delete', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Default colours and the booking site address.
	 *
	 * @param array $settings Stored settings.
	 */
	private static function render_settings( array $settings ) {
		?>
		<h2><?php esc_html_e( 'Widget colours', 'anybook' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="anybook_save_settings" />
			<?php wp_nonce_field( 'anybook_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="anybook_accent"><?php esc_html_e( 'Main colour', 'anybook' ); ?></label></th>
					<td><input type="text" id="anybook_accent" name="anybook_accent" value="<?php echo esc_attr( $settings['accent'] ); ?>" placeholder="#0b6e4f" pattern="#([0-9a-fA-F]{3}){1,2}" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="anybook_text"><?php esc_html_e( 'Text colour', 'anybook' ); ?></label></th>
					<td><input type="text" id="anybook_text" name="anybook_text" value="<?php echo esc_attr( $settings['text'] ); ?>" placeholder="#1f2933" pattern="#([0-9a-fA-F]{3}){1,2}" /></td>
				</tr>
			</table>
			<details>
				<summary><?php esc_html_e( 'Advanced', 'anybook' ); ?></summary>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="anybook_site"><?php esc_html_e( 'Booking site address', 'anybook' ); ?></label></th>
						<td>
							<input type="url" id="anybook_site" name="anybook_site" class="regular-text" value="<?php echo esc_attr( Settings::site() ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave as https://app.anybookapp.com unless Anybook gave you a test address.', 'anybook' ); ?></p>
						</td>
					</tr>
				</table>
			</details>
			<?php submit_button( __( 'Save settings', 'anybook' ) ); ?>
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
			'<li><span class="dashicons %1$s" style="color:%2$s" aria-hidden="true"></span> <span class="screen-reader-text">%3$s</span>%4$s</li>',
			esc_attr( $ok ? 'dashicons-yes-alt' : 'dashicons-warning' ),
			esc_attr( $ok ? '#00a32a' : '#d63638' ),
			esc_html( $ok ? __( 'OK:', 'anybook' ) : __( 'To fix:', 'anybook' ) ),
			esc_html( $text )
		);
	}
}
