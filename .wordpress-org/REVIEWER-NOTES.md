# Notes for the WordPress.org plugin reviewers (AB#287, WPP-03)

These notes are for the owner to paste into WordPress.org's private reviewer field when he submits a version. They are not
part of the plugin: `.wordpress-org/` is left out of the release zip (`.gitattributes`), and only its images go to the
plugin page's assets. The login's password is not written here. It is the demo's reviewer password,
`BOOKING_DEMO_REVIEWER_PASSWORD` in the demo stack's settings. The owner adds it to the reviewer field himself.

## Text to paste (fill in the password)

> **Trying the plugin with a demo business**
>
> Anybook is a hosted booking service; the plugin connects a WordPress site to a business on it. We keep a demo business for
> reviewers on our public demo (made-up data, test payments only, no email leaves the demo, reset every night at 08:00 UTC):
>
> - Business code: **wp-reviewers-demo**
> - Owner login: **wp-reviewers@demo.anybookapp.com**
> - Password: **(the password)**
>
> 1. Install and activate the plugin on an https test site (for example WordPress Playground). A site on `http://localhost`
>    cannot be connected to the demo.
> 2. Go to **Settings, Anybook** and click **Connect to Anybook**.
> 3. Anybook opens: sign in with the login above and click **Allow**. You come back to WordPress with the business connected,
>    and your site is added to the business's allowed websites.
> 4. Add the **Anybook booking** block (or the `[anybook]` shortcode) to a page and view it: the booking widget shows the
>    demo's cabins (by the night) and kayaks (by the hour), with booked and free days next month.
> 5. Book as a customer if you like: the payment step offers **Complete demo payment**, and nothing is charged.
>
> The nightly reset removes the sites you allowed, so connect again on another day.

## Before submitting

- The demo install must have `BOOKING_DEMO_REVIEWER_PASSWORD` set, and must be deployed with Anybook PR #970 (merged
  4fdb3a39). Then the business is there after every reset. Check it by signing in once with the login above on
  https://demo.anybookapp.com.
- The password is used for this login only. Change it if it has been shared further than the reviewers.
