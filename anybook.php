<?php
/**
 * Plugin Name:       Anybook – Online Booking for Rentals, Tours, Classes & Appointments
 * Description:       Take bookings and card payments on your website with the Anybook booking widget.
 * Version:           0.1.4
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Anybook
 * Author URI:        https://anybookapp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       anybook
 * Domain Path:       /languages
 *
 * @package Anybook
 */

/*
 * Anybook Online Booking is free software: you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation, either version 2 of the License, or (at
 * your option) any later version. It is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Loaded only by WordPress: a direct request to this file must do nothing.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ANYBOOK_VERSION', '0.1.4' );
define( 'ANYBOOK_FILE', __FILE__ );
define( 'ANYBOOK_DIR', plugin_dir_path( __FILE__ ) );

require_once ANYBOOK_DIR . 'includes/class-settings.php';
require_once ANYBOOK_DIR . 'includes/class-credit.php';
require_once ANYBOOK_DIR . 'includes/class-client.php';
require_once ANYBOOK_DIR . 'includes/class-connection.php';
require_once ANYBOOK_DIR . 'includes/class-booking-page.php';
require_once ANYBOOK_DIR . 'includes/class-assets.php';
require_once ANYBOOK_DIR . 'includes/class-renderer.php';
require_once ANYBOOK_DIR . 'includes/class-shortcode.php';
require_once ANYBOOK_DIR . 'includes/class-block.php';
require_once ANYBOOK_DIR . 'includes/class-rest.php';
require_once ANYBOOK_DIR . 'includes/class-admin.php';
require_once ANYBOOK_DIR . 'includes/class-plugin.php';

\Anybook\Plugin::init();
