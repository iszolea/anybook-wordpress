=== Anybook Online Booking ===
Contributors: anybookapp
Tags: booking, booking calendar, appointments, reservations, online booking
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Take bookings and card payments on your website with the Anybook booking widget.

== Description ==

Anybook Online Booking puts your Anybook booking widget on your WordPress site. Guests choose dates, times or a service, book and pay by card without leaving your website.

The plugin is a small connector. Prices, the calendar, bookings, payments and emails all stay on Anybook, so improvements to the widget reach your site without a plugin update.

* **Connect in two clicks.** Click Connect to Anybook, sign in as your business's Owner and click Allow. Anybook lets your website show the widget. No keys or passwords are copied.
* **A booking page made for you.** The plugin can make a "Book online" page, which Anybook uses for your guests' email links. It stays a draft until the Owner clicks Allow on Anybook.
* **A block and a shortcode.** Show all your units, one unit, your appointments or one service, in your colors and with your own button wording, or an availability overview: a calendar of the weeks ahead where guests click a free day to book.
* **Status at a glance.** The settings page shows whether your site is allowed, where your guest page is and whether online booking is on.
* **Friendly with speed plugins.** The widget's script loads only on pages that show it, in the page's head, and asks common speed plugins to leave it alone. If the widget does not appear, exclude booking-widget.js and booking-overview.js from delaying and combining in your speed plugin.

You need an Anybook account. Anybook is a paid service; the plugin itself is free and has no locked features. See [anybookapp.com](https://anybookapp.com) for plans and prices.

= Getting help =

Anybook has no phone or email help desk. Help comes in three ways:

1. **The help assistant in Anybook.** This is the fastest. Click the round **?** button in the top bar of Anybook, type your question and press Enter. It answers from the Anybook user guide.
2. **The Anybook user guide.** It covers every feature, including this plugin. Open it from the help window in Anybook (the **User guide** button).
3. **The support forum on WordPress.org.** Post here if the first two do not solve it. It is watched, but a reply may take a day.

= The Anybook booking block =

Add the **Anybook booking** block anywhere in the block editor. In its sidebar choose what it shows (all your units, one unit, your appointments or one service, listed from your Anybook account), its colors (your theme's palette is offered), the wording of the booking button and two headings, and whether it opens guests' email links. Choose **Availability overview** to show which days are free in the weeks ahead instead; then choose the weeks shown (2 to 26, 12 if left alone), whether prices show, which units (all of them if none is ticked) and whether appointments have one row per service or one row per team member for one service. The overview uses the colors on the plugin's settings page. A guest who clicks a free day gets the booking widget. The editor shows a preview card; the live widget shows on the published page. A Shortcode block holding `[anybook ...]` can be turned into the block, keeping its choices.

= Shortcode =

For the classic editor and page builders, `[anybook]` shows the same widget with the same choices:

* `show="all"`, `show="unit"`, `show="appointments"` or `show="service"`: what to show (all units if left out).
* `unit="belle"`: one unit (its code in Anybook).
* `service="cut"`: one service; `services="yes"` also means appointments.
* `accent="#0b6e4f"` and `text="#1f2933"`: colors.
* `guest_links="yes"`: this widget opens guests' email links when a page has several.
* `button_text="Book now"`, `details_heading="About you"`, `dates_text="Pick your dates"`: wording.

The availability overview: `[anybook show="overview"]`, with these choices:

* `weeks="12"`: how many weeks it shows, 2 to 26 (12 if left out).
* `prices="yes"`: show prices.
* `units="belle,kayak"`: only these units (their codes in Anybook, separated by commas); all units if left out.
* `rows="people" service="..."`: one row per team member for that one service (its id or code in Anybook); otherwise one row per service.

= Page builders =

In Elementor, put the shortcode in a Shortcode widget; in Divi, in a Code or Text module. The plugin finds the shortcode in both, so the widget's script loads in the page's head and the settings page knows your booking page still holds the widget. With other page builders the widget works too, but its script loads at the end of the page; if a guest's email link does not open the booking, put the shortcode in the page's main content as well, or ask the builder's support to call the `anybook_page_has_widget` filter.

== External services ==

This plugin connects your website to **Anybook** (https://anybookapp.com), the booking service that takes your bookings and payments. Without Anybook the plugin does nothing.

Your website contacts Anybook only after an administrator or editor has done one of these things:

* **When you click Connect to Anybook**, your browser goes to the Anybook booking site with your website's name and address, the address of the plugin's settings page to come back to, a single-use check value, and the address of your booking page if you chose to make one. Anybook sends your browser back with your business's code and name.
* **When you connect or enter a business code, and when you open the plugin's settings page**, your web server asks Anybook's public API whether your business exists and whether your website is allowed, sending your business code and your website's address. No personal data is sent. The answer is kept for ten minutes (a failure for one minute).
* **When someone edits a page with the Anybook booking block**, your web server asks Anybook's public API for your business's units and services (sending your business code) to fill the block's choices; the answer is kept for ten minutes.

Your visitors' browsers contact Anybook as well:

* **On pages that show the booking widget**, each visitor's browser loads the widget script from the Anybook booking site (https://site.anybookapp.com/widget/booking-widget.js), and on pages that show the availability overview its script (https://site.anybookapp.com/widget/booking-overview.js) and, once the overview shows, the script that opens the widget from it and the widget's own script (booking-overview-open.js and booking-widget.js, from the same address). The widget loads further parts of itself, named booking-*.js, from the same address as a guest moves through it. Each of these requests sends Anybook the visitor's IP address, browser details and the address of the page, even if they do not book. When a guest books, what they type in the booking form (such as name, email address, phone number and booking details) is sent from their browser to Anybook. For card payments the widget loads Stripe (https://stripe.com), and card details go to Stripe. The widget stores nothing in the browser.

The plugin loads remote scripts only on pages that show the widget or the availability overview, never in the WordPress admin, and only once a business is connected. They are part of the Anybook service and are the widget itself, so they are not in the plugin. They load over https from the booking site's plain address, so changes to the widget reach your site without a plugin update.

Anybook terms of service: https://anybookapp.com/terms
Anybook privacy policy: https://anybookapp.com/privacy
Stripe terms: https://stripe.com/legal and privacy policy: https://stripe.com/privacy

The plugin adds no tracking and stores no booking, guest or payment details on your website. It suggests words for your privacy policy under Settings, Privacy.

== Source code and build ==

The folder `build/booking/` holds generated files, made from human-readable source that ships in this plugin.

* **Source:** `blocks/booking/` is the source of everything in `build/booking/`, including the minified `build/booking/index.js`. Start with `blocks/booking/index.js`, `edit.js`, `shortcode.js`, `editor.scss` and `block.json`.
* **Build set-up:** `package.json` and `package-lock.json`, in the plugin's main folder, list the exact tools and versions.
* **Tools:** Node.js 20 with npm, and `@wordpress/scripts` 30.27.0 (webpack and Sass), which `npm ci` installs.

To rebuild the block, run these two commands in the plugin's main folder:

    npm ci
    npm run build

`npm run build` runs `wp-scripts build --webpack-src-dir=blocks --output-path=build` and writes `build/booking/`. All the plugin's other files are plain PHP and need no build. The Anybook widget scripts are not part of this plugin: see External services.

== Installation ==

1. Install the plugin from Plugins, Add New, and activate it.
2. Open **Anybook** in the admin menu and click **Connect to Anybook**.
3. Sign in to Anybook as your business's Owner and click **Allow**.
4. Add the **Anybook booking** block to a page, or use the "Book online" page the plugin made.

Staff who are not the Owner can enter the business code instead (Anybook shows it under Settings, Online booking); the Owner then adds the website to allowed websites in Anybook.

== Frequently Asked Questions ==

= Where do I get help? =

The fastest help is the help assistant inside Anybook: click the round **?** button in the top bar and ask your question. The Anybook user guide (open it from the help window in Anybook) covers every feature. You can also post on the support forum here on WordPress.org: it is watched, and a reply may take a day. There is no phone or email support.

= How do I connect the plugin to Anybook? =

Open **Anybook** in the admin menu and click **Connect to Anybook**. Sign in to Anybook as your business's Owner, check that the address shown is your website, and click **Allow**. You come back to WordPress, which says "Connected to Anybook". Anybook adds your website to its allowed websites for you. No keys or passwords are copied.

If you are not the Owner, enter your business code under "Or enter your business code" and click **Save code** (Anybook shows the code under Settings, Online booking). That connects the plugin only: the Owner must then add this website to allowed websites in Anybook (Settings, Online booking). Anybook counts a website with "www" and without "www" as two different addresses, so add the one your site uses.

= How do I choose which unit (or units) a booking block shows? =

Select the **Anybook booking** block. In its sidebar, under **What to show**, choose **All units**, **One unit**, **Appointments** or **One service**. For **One unit**, pick the unit from the list, which comes from your Anybook account. If the list cannot load, press **Try again**, or type the unit's code as Anybook shows it. For several units at once, choose **Availability overview** and tick the units you want under **Units**; with none ticked, all your units show. In a shortcode, use `[anybook show="unit" unit="belle"]`, or for the overview `[anybook show="overview" units="belle,kayak"]`.

= How do I set the colors, and what is a hex code? =

A hex code is a color written as a # and 3 or 6 letters or digits (0 to 9, a to f), for example `#0b6e4f` or `#fff`. Set the colors for every widget on the plugin's settings page (Anybook in the admin menu, **Widget colors**: Main color and Text color). A block sets its own under **Colors** in its sidebar, and a shortcode with `accent="#0b6e4f"` and `text="#1f2933"`. A block or shortcode color wins over the settings page.

If a value is not a color like that, the settings page does not save it: it says so and keeps the color saved before. In a block or shortcode a bad value is ignored, and the settings page color is used (or the widget's own, if there is none).

= How do I set my customer page? =

Your customer page is the page on your website where guests' email links open, so they can pay for, see or change their booking. Anybook calls it your customer page, and the plugin's settings page calls it your guest page. The Owner sets it, and it needs the Anybook booking block or the `[anybook]` shortcode on it.

The easiest way: tick **Make a booking page for me ("Book online") and use it as the guest page** before you click **Connect to Anybook**, then tick the guest page choice on Anybook before you click **Allow**. If your site has no guest page yet, the plugin's settings page shows **Set your guest page**: the Owner clicks it and ticks the same choice on Anybook. You can also type the page's address into **Your customer page** in Anybook (Settings, Online booking). The page must be on your own website, on the allowed websites list, and its address must start with https. Until it is set, guests' email links do not work.

= The widget does not show. What should I check? =

Open **Anybook** in the admin menu: the Status list shows which of these is wrong.

* The plugin is not connected yet.
* Your business is still waiting for Anybook's approval.
* Online booking is off in Anybook (Settings, Online booking).
* This website is not on your allowed websites in Anybook. "www" and no "www" count as different addresses.
* Your WordPress admin and your website have different addresses, so Connect cannot add the right one: add your website's address in Anybook yourself.
* The "Book online" page is still a draft, or someone changed it so it no longer holds the widget.
* A speed plugin delays or combines the widget's script. Exclude booking-widget.js and booking-overview.js in its settings.
* Your site sends a content security policy that blocks it. The settings page lists the lines your policy needs.
* Your business has no units or services guests can book online.
* The shortcode is in a page builder the plugin does not know. Put it in the page's main content as well.

The settings page asks Anybook again at most every ten minutes; **Check again** asks at once. If the widget still does not show, ask the help assistant in Anybook or post on the support forum.

= Does the plugin store a password or key? =

No. It stores your business's public code and name, the booking site address, the booking page it made and your default colors.

= What happens when I uninstall the plugin? =

Its settings are removed, on every site of a network. Your booking page stays (it is your page) and your Anybook account is not changed. Remove the website from allowed websites in Anybook if you no longer use it.

= My site sends a content security policy. =

The settings page lists the lines your policy needs. Give them to whoever manages your site's security headers.

= I run a network of sites in folders (example.com/shop, example.com/blog). =

Every site of such a network has the same address (example.com) as far as browsers are concerned, so whatever your business allows for one site, it allows for all of them: another site of the network could show your booking widget and read what guests type in it. The settings page warns about this. Give your booking website its own domain or subdomain (a network that uses subdomains is fine).

= Can I use a booking site other than site.anybookapp.com? =

The settings page accepts only https://site.anybookapp.com, and only from an administrator who may add scripts to the site (on a network, the super admin). On a local or development WordPress (environment type `local` or `development`) it also accepts localhost. For any other address, for example your own test install of Anybook, add `define( 'ANYBOOK_SITE', 'https://your-address' );` to wp-config.php.

= Where is the source code of the block? =

It ships with the plugin, in `blocks/booking/`. See Source code and build, above, for how `build/booking/` is made from it.

== Screenshots ==

1. The Anybook settings page once connected: the status of your business, your website and your guest page.
2. The Anybook booking block in the editor, with its choices in the sidebar.
3. The booking widget on the "Book online" page.

== Changelog ==

= 0.1.2 (not released) =
* The readme now has a Getting help section and answers to common questions: connecting, choosing units, colors, the customer page and a widget that does not show.

= 0.1.1 =
* New: an availability overview in the block and shortcode, a calendar of the weeks ahead where guests click a free day to book.
* The readme now explains where the block's source is and how to build it, and `package-lock.json` ships with the plugin.
* The settings page loads its small style and script the WordPress way instead of inline.
* Now needs WordPress 6.6, which the block's editor script depends on.
* Connects to Anybook's live booking site, site.anybookapp.com.
* The Connect reminder shows on the Plugins screen only.

= 0.1.0 =
* First version: Connect to Anybook, settings and status, the Anybook booking block, the `[anybook]` shortcode, the "Book online" page, privacy policy text, multisite uninstall.
