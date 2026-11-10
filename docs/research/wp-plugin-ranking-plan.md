# WordPress.org search ranking plan for the Anybook plugin

Date: 2026-11-10. For iszolea/anybook-wordpress#8. Short on purpose. WordPress.org does not publish its ranking formula, so this is what is known to matter and what the research showed the leaders doing (see `wp-plugin-pitch-research.md`). Treat it as a plan to test, not a promise.

## 1. Name and short description (the biggest lever)

- **Name:** "Anybook – Online Booking for Rentals, Tours, Classes & Appointments" (in the draft readme). It keeps the brand and puts four search words in the name, as LatePoint, Amelia and Bookly do. Do not stuff more: the directory rejects names that are keyword lists. If the reviewers object, fall back to "Anybook – Online Booking Calendar for Rentals & Appointments".
- **Short description (max 150, draft is 133):** what you can book, where guests book, and the price promise. Words people type first: booking, appointment, rental, tour, calendar.
- **First line of the description** repeats the same words in a sentence a person would say. The directory searches the full description too.
- **Never change the name for ranking tests more than once a quarter.** Constant renaming costs more than it gains.

## 2. Tags (five count, the first five shown)

booking, booking calendar, rental booking, tour booking, appointment booking.
- The two biggest tags are used by 12 to 23 of 48 plugins, so we compete there but name-match helps.
- "rental booking" and "tour booking" are used by fewer than 5; that is where Anybook can rank high sooner.
- Review the five after 60 days. Swap one if search terms in the data tell us to.

## 3. Tested up to, and release rhythm

- Keep **Tested up to** on the current WordPress version at every release. The leaders are all on 7.1.x and were updated within the last two weeks. Test on each new WordPress beta/RC and release a "tested up to" bump within a week of the stable release.
- Release **at least once a month** (even a small fix). Each release is also a reason to ask for a review (below) and to refresh the changelog. Never release only to change the date.
- Keep the readme and `build/` in step (the repo's release script already checks this).
- The owner releases with `upload-plugin.ps1`; nothing is released until the owner says so.

## 4. Images and demo

- New banner and 8 screenshots (see the draft note). The leaders have about 11 screenshots, so the aim is 8 now and more as features are shown.
- Link a live demo in the first screen **only when `demo.anybookapp.com` is ready to be public** (an open owner question). A demo is the strongest trust signal the leaders use.

## 5. Reviews (the slowest and the most valuable)

- Hosted-service plugins get few reviews (SimplyBook.me: 30,000 installs, 1 rating), so a handful of honest 5-star reviews is a real advantage.
- **Ask once, a month after a business takes its first online booking,** with a plain message and the link `https://wordpress.org/support/plugin/anybook/reviews/#new-post`. Only businesses that actually use the plugin. Anybook sends nothing to external users unless the owner says so, so the owner decides when and how this message goes out (an in-app note on the Online booking page is the lightest way).
- **Never** pay for, trade for, fake or pre-write reviews, and never ask only happy customers in a way that screens out unhappy ones (WordPress.org forbids review gating). The owner or the business itself can reply to every review.

## 6. Support forum

- Answer **every** thread, aim within one day. The directory shows resolved threads and the support "response" signal.
- `f/anybook/forum_watch` (daily Windmill job) drafts replies. Keep it as drafts for a person to approve and post; do not auto-post. Mark a thread resolved when the user confirms.
- Turn repeated questions into FAQ entries in the readme (this is how #7's FAQ was written).

## 7. Other things that help

- A clear, complete **External services** section (already thorough) and no review problems with the directory.
- A website page for WordPress users, linked from the readme, that matches the readme claims (the website session owns the website).
- Active installs grow from the site's own WordPress guide (the website's WordPress guide should name the plugin first; the audit raised this as WEB-12).

## 8. What to measure

Once a week for the first two months, write down: position in the directory for "booking", "booking calendar", "rental booking", "tour booking" and "appointment booking" (search, note the rank); active installs; ratings; resolved threads. Change one thing at a time so the effect can be read.

## Owner decisions needed

1. The name (and whether to keep the long form).
2. When `demo.anybookapp.com` can be linked.
3. How the review request is sent (in-app note, or nothing), since Anybook sends nothing external without the owner.
