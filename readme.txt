=== Anybook Online Booking ===
Contributors: anybookapp
Tags: booking, booking calendar, appointments, reservations, online booking
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Take bookings and card payments on your website with the Anybook booking widget.

== Description ==

Anybook Online Booking puts your Anybook booking widget on your WordPress site. Guests choose dates, times or a service, book and pay by card without leaving your website.

The plugin is a small connector. Prices, the calendar, bookings, payments and emails all stay on Anybook, so improvements to the widget reach your site without a plugin update.

* **Connect in two clicks.** Click Connect to Anybook, sign in as your business's Owner and click Allow. Anybook lets your website show the widget. No keys or passwords are copied.
* **A booking page made for you.** The plugin can make a "Book online" page, which Anybook uses for your guests' email links.
* **A block and a shortcode.** Show all your units, one unit, your appointments or one service, in your colours and with your own button wording.
* **Status at a glance.** The settings page shows whether your site is allowed, where your guest page is and whether online booking is on.
* **Works with speed plugins.** The widget's script is loaded only on pages that show it and is kept out of WP Rocket, LiteSpeed Cache, Autoptimize, SiteGround Optimizer, W3 Total Cache, Jetpack Boost and Cloudflare Rocket Loader optimisations.

You need an Anybook account: see [anybookapp.com](https://anybookapp.com).

= The Anybook booking block =

Add the **Anybook booking** block anywhere in the block editor. In its sidebar choose what it shows (all your units, one unit, your appointments or one service, listed from your Anybook account), its colours (your theme's palette is offered), the wording of the booking button and two headings, and whether it opens guests' email links. The editor shows a preview card; the live widget shows on the published page.

= Shortcode =

For the classic editor and page builders such as Elementor or Divi, `[anybook]` shows the same widget with the same choices:

* `show="all"`, `show="unit"`, `show="appointments"` or `show="service"`: what to show (all units if left out).
* `unit="belle"`: one unit (its code in Anybook).
* `service="cut"`: one service; `services="yes"` also means appointments.
* `accent="#0b6e4f"` and `text="#1f2933"`: colours.
* `guest_links="yes"`: this widget opens guests' email links when a page has several widgets.
* `button_text="Book now"`, `details_heading="About you"`, `dates_text="Pick your dates"`: wording.

== External services ==

This plugin connects your website to **Anybook** (https://anybookapp.com), the booking service that takes your bookings and payments. Without Anybook the plugin does nothing.

* **When you click Connect to Anybook**, your browser goes to the Anybook booking site with your website's name and address, and the address of your booking page if you chose to make one. Anybook sends your browser back with your business's code and name.
* **When you connect or enter a business code, and when you open the plugin's settings page**, your web server asks Anybook's public API whether your business exists and whether your website is allowed, sending your business code and your website's address. No personal data is sent.
* **When someone edits a page with the Anybook booking block**, your web server asks Anybook's public API for your business's units and services (sending your business code) to fill the block's choices; the answer is kept for ten minutes.

The plugin contacts Anybook from your server only after an administrator has done one of these things.
* **On pages that show the booking widget**, each visitor's browser loads the widget script from the Anybook booking site (https://app.anybookapp.com/widget/booking-widget.js). When a guest books, what they type in the booking form (such as name, email address, phone number and booking details) is sent from their browser to Anybook. For card payments the widget loads Stripe (https://stripe.com), and card details go to Stripe.

Anybook terms of service: https://anybookapp.com/terms
Anybook privacy policy: https://anybookapp.com/privacy
Stripe terms: https://stripe.com/legal and privacy policy: https://stripe.com/privacy

The plugin adds no tracking and stores no booking, guest or payment details on your website.

== Installation ==

1. Install the plugin from Plugins, Add New, and activate it.
2. Open **Anybook** in the admin menu and click **Connect to Anybook**.
3. Sign in to Anybook as your business's Owner and click **Allow**.

Staff who are not the Owner can enter the business code instead (Anybook shows it under Settings, Online booking); the Owner then adds the website to allowed websites in Anybook.

== Frequently Asked Questions ==

= Does the plugin store a password or key? =

No. It stores your business's public code and name, the booking site address, the booking page it made and your default colours.

= What happens when I uninstall the plugin? =

Its settings are removed, on every site of a network. Your booking page stays (it is your page) and your Anybook account is not changed. Remove the website from allowed websites in Anybook if you no longer use it.

= My site sends a content security policy. =

The settings page lists the lines your policy needs. Give them to whoever manages your site's security headers.

== Changelog ==

= 0.1.0 =
* First version: connect, settings and status, booking page, shortcode.
