# The WordPress.org plugin page: research and plan (2026-11-10)

For issue #8 (owner, 2026-11-10): make wordpress.org/plugins/anybook the clearest and strongest page among booking
plugins. This note is for the owner to approve before anything is released.

## How the research was done

* **Source:** the public WordPress.org plugin directory API, which serves the same data as the plugin pages.
* **Searches:** "booking", "appointment", "rental booking", "tour booking" and "booking calendar", 15 results each, made on 2026-11-10. That is five requests in all, with a named user agent.
* **Plugins:** 44 different ones. WooCommerce is left out of the numbers below, because it only shows up for "booking".
* **What was read, for each plugin:**
  * its name, short description, tags and the first screen of its description;
  * how many FAQ answers and screenshots it has, and whether it has a banner;
  * active installs, rating and number of ratings;
  * support threads resolved in the last two months;
  * its "Tested up to" version and its last update.
* **Nothing private was used,** and no AI was run on other companies' data.

## What the leaders look like

The biggest by active installs:

| Plugin | Installs | Rating | Type |
|---|---|---|---|
| LatePoint | 100,000+ | 98% | Appointments |
| Amelia | 90,000+ | 92% | Appointments and events |
| Events Manager | 60,000+ | 84% | Events |
| Bookly | 60,000+ | 88% | Appointments |
| Simply Schedule Appointments | 50,000+ | 100% | Appointments |
| Booking Calendar | 40,000+ | 96% | General |
| WP Travel Engine | 20,000+ | 98% | Tours |
| WP Booking System | 10,000+ | 98% | Daily rentals |
| MotoPress Hotel Booking | 10,000+ | 74% | Hotels and vacation rentals |

**Common to almost all of the top 20:**

* **Name:** a brand plus the words people search for, joined by a dash or bar (12 of 20). For example, "Appointment Booking Plugin – LatePoint | Calendar & Scheduling for WordPress".
* **Short description:** 130 to 150 characters (the median is 134). It says what you can take bookings for, then gives one benefit. Several end with a call to action ("Start booking today!").
* **Screenshots:** about 11 or 12 (the median is 11.5). Several have more than 20.
* **FAQ:** about 11 answers.
* **Banner:** every one of the 44 has one.
* **Tested up to:** almost all show the current version (7.1.4). Most were updated in the last two weeks; the directory ranks plugins that are maintained.
* **Live demo link:** 14 of 20.
* **Video:** 10 of 20.
* **Phones:** 15 of 20 say the booking works on phones.
* **"Pro" or "Premium" upsell:** 17 of 20 are free plugins that sell a paid version inside WordPress.

**Rare, and so a way to stand out:**

* **"No commission" or a flat price:** only 2 of 20 say it.
* **Comparing themselves with other tools:** 3 of 20.
* **One system for very different booking types** (nights, days, hours, items, seats, appointments): most plugins are built for one type. LatePoint, Amelia and Bookly do appointments, WP Travel Engine does tours, and WP Booking System does daily rentals. Booking Calendar and BA Book Everything come closest.

**Tags.** These are the most used across the 44 plugins:

| Tag | Plugins using it |
|---|---|
| booking | 23 |
| booking calendar | 12 |
| calendar | 12 |
| reservation | 11 |
| appointments | 10 |
| appointment booking | 10 |
| booking system | 7 |
| tour booking | 5 |
| travel booking | 5 |

Fewer than 5 use "rental booking". The directory shows only the first five tags.

## The closest kind: plugins that connect a website to an online booking service

| Plugin | Installs | Ratings | Trial, as its page states it |
|---|---|---|---|
| SimplyBook.me | 30,000+ | 1 | A free account, then a 14-day trial of the paid features. After that you choose a free plan |
| Booqable | 1,000+ | 10 | A 14-day free trial, no credit card required |
| TuriTop | 500+ | 2 | A 14-day free trial, or book a demo |
| Twice Commerce | 300+ | 2 | A 3-day free trial, then the first month for US$1, then from US$29 a month |

All four offer a trial. None gets many reviews, and SimplyBook.me has 30,000+ installs but only one rating. A plugin that only connects to a service gets few reviews unless someone asks for them.

## What we change (draft readme in this pull request)

1. **Name:** "Anybook – Online Booking for Rentals, Tours, Classes & Appointments". It keeps the brand and adds the four kinds of business that search for us.
2. **Short description:** 133 characters: "Rentals, tours, classes and appointments in one booking calendar. Guests book and pay on your own website. Flat price, no commission." It covers every booking type, says guests book and pay on your own site, and gives the flat price with no commission.
3. **Tags (5):** booking, booking calendar, rental booking, tour booking, appointment booking.
   * Two are the biggest searches.
   * Two are where we are strongest and the field is thinner.
   * The fifth is appointments, the biggest category.
4. **The first screen:** who it's for and what you get, before any setup detail:
   * nights, days, hours, items and appointments in one calendar;
   * guests stay on your site;
   * deposits, bonds, waivers and a manifest;
   * every feature on every plan, and no commission.
   It also says plainly that Anybook is a paid service, with its price. WordPress.org asks service plugins to say this.
5. **Feature list:** grouped the way a business owner thinks: take bookings, get paid, paperwork, run the day, your website. Every line is something the app does today (checked against the user guide and the website).
6. **FAQ:** the buyer's questions come first:
   * What does it cost?
   * Is there a free trial?
   * Does it take a commission?
   * Can I try it first?
   * What can I book with it?
   * Can I bring my bookings from another system? (answer: no self-service import yet)
   Then the help and setup questions from #7. The technical and privacy sections stay, lower down.
7. **"Tested up to" and releases:** keep "Tested up to" on the newest WordPress at every release. Release at least once a month, even for a small fix, so the plugin shows as maintained.
8. **Reviews:** a month after a business takes its first online booking, ask it once to leave a review on WordPress.org. Never offer anything in return; WordPress.org forbids that.
9. **Support forum:** answer every thread within a day; the forum watcher drafts each reply. The directory shows how many threads are resolved.

## Banner and screenshots (to make, then for the owner to approve)

**Banner (1544x500 and 772x250).** The current one has only the logo and "Online booking and card payments for your WordPress site". The new one should show:
* the widget on a phone, on the right;
* on the left: "Nights, days, hours, items, appointments. Guests book and pay on your site. No commission.";
* the same colors and logo.

**Screenshots.** We have 3; the leaders have about 11. Take 8 from the public demo (demo.anybookapp.com), with real-looking made-up data. The current screenshot 3 shows an almost empty calendar.

1. The widget on a phone: choosing dates, with prices on the calendar.
2. Checkout: deposit now, the rest later, paid by card.
3. The availability overview: free days for the weeks ahead.
4. Booking several units at once (booking groups).
5. The online waiver being signed.
6. The daily manifest in Anybook: today's arrivals, check-in, bonds.
7. The Anybook booking block in the editor, with its sidebar choices.
8. The plugin's settings page once connected: everything green.

The screenshot captions in the draft readme already match this list. Don't release until the eight images exist.

## Free trial: options for the owner

**The question:** should people who come from WordPress get a free trial?

**Today:**
* **No free trial:** a business pays at sign-up, so the database only holds paying businesses (owner, 2026-10-30).
* **Opening special:** US$10 a month for the first 6 months, for a limited time.
* **To try Anybook first:** people use the demo.

| Option | What the business gets | For | Against |
|---|---|---|---|
| A. Keep it as it is | The demo to try; pay $10 at sign-up | Simplest. Only serious businesses sign up. The $10 special is already low | All four service plugins above offer a trial, so we look stricter |
| B. 14-day trial for everyone, card at sign-up | 14 days free, then the $10 special starts. Cancel before day 14 and pay nothing | Matches what WordPress users expect. Asking for a card keeps casual sign-ups away | Some unpaid accounts to clean up. The app's billing must support a trial (Stripe does) |
| C. 14-day trial only for sign-ups from the plugin | As B, but only through the plugin's Connect or sign-up link | Something to say on WordPress.org without changing the website | Two sign-up paths to explain and test. People will notice the difference |
| D. 30-day trial, card at sign-up | As B, for 30 days | Long enough to set up and take real bookings | A month of free service each time, and more accounts that never pay |
| E. First month $1 (like Twice Commerce) | $1, then the $10 special | Almost free, and the card is proven at once | Odd next to a price that is already $10 |

**Recommendation:** A for now, with the live demo made prominent on the plugin page. Revisit B after the first month of plugin installs. The $10 opening special already does what a trial does, and the owner wants no unpaid accounts. Nothing changes until the owner decides. If the owner chooses B, C or D, the app's sign-up and billing change first, then the website, Terms and plugin page say the same thing on the same day.

## Review notes (2026-11-10, second pass)

* **Two claims were removed from the readme because the product does not support them as written:** a customer-facing "upload your WP Booking System export" import (the WP Booking System importer is a command-line tool the Anybook team runs; there is no self-service import), and "waivers with several signers" (the user guide describes one signer confirmed by an emailed code). "Items counted from stock" became "places counted for each time", the user guide's wording.
* **Apple Pay and Google Pay are dropped from the readme (coordinator, 2026-11-10):** the widget pays with Stripe's Payment Element on the business's own site and Anybook does not register those domains with Stripe, so wallets will not reliably show. A backlog item may be raised; add the claim back only when it works. The QR-code line (user guide, "Take a charge or the balance by QR code") is still worth a check.
* **The opening special's length disagrees:** website and this readme say 6 months, the user guide says 12 months. The owner must pick one.
* The full table of 48 plugins, the ranking plan and the trial options (with billing effects) are in `C:\Users\jnich\Projects\Anybook\research\`: `wp-plugin-pitch-research.md`, `wp-plugin-ranking-plan.md`, `wp-plugin-free-trial-options.md`.

## Banner proposal and screenshots (status, 2026-11-10)

* **Proposed banner:** `.wordpress-org/proposed/banner-1544x500.png` and `banner-772x250.png`, drawn by `tools/draw-banner-proposal.mjs` (same navy and mint, a drawn widget on a phone, the line "Nights, days, hours, seats and appointments." and "Guests book and pay on your own website. Flat price, no commission."). The owner-approved banner in `.wordpress-org/` is untouched; if the owner prefers the new one, copy the two files up one folder. It is an illustration of plain shapes, not a screenshot, and the widget in it is drawn (the demo houseboat and price are made up).
* **Screenshots:** the demo app (demo.anybookapp.com) signs in with one click ("Sign in as Cedar Cove Boat Rentals (Demo)" on its login page), so its own pages can be captured. **Screenshot 6 (the daily manifest) is taken** (`.wordpress-org/proposed/screenshot-6.png`, 1280x800, made-up customers), and the calendar is a spare candidate (`candidate-calendar.png`). Screenshots 1 to 5 are the widget on a customer page and wait for the demo customer-page fix (PBI 1.11). Screenshots 7 and 8 (block in the editor, settings page) already exist as the current screenshot-2 and screenshot-1. Do not add a public "Try the demo" link until PBI 1.11 lands.
