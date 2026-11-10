# Free trial for WordPress users: options and their effect on billing

Date: 2026-11-10. For iszolea/anybook-wordpress#8. **Nothing is changed until the owner decides.** Facts are from the user guide, `docs/stripe-billing-setup.md` and `docs/decisions.md` in the Anybook repository, and the published website.

## Where things stand

- **Today there is no free trial.** A new business pays its first month at sign-up (Stripe's checkout), is approved automatically when the payment succeeds, and its plan is active at once (user guide, Getting started; decision #322, 2026-11-04).
- **A trial mechanism exists in the app, for the older flow:** a 30-day trial that starts when a business is approved, with no card, set by `STRIPE_BILLING_TRIAL_DAYS` (default 30). It is Anybook's own trial, not a Stripe trial. In the trial everything works and usage beyond the included amount is not charged. If it ends with no plan, the business becomes read-only; bookings and customers are not affected. Choosing the plan in the last 3 days charges the card at once. Platform staff can start or extend a trial.
- **Price:** one plan, US$15, tiers by bookings (US$15 / 39 / 79; Custom above 3,000). The opening special is US$10 a month on any plan, "for a limited time". Stripe holds both prices; Anybook stores no amount.
- **The competitors in the WordPress directory that connect to a hosted service all let you try it:** SimplyBook.me has a free plan, Booqable and TuriTop a free trial, Twice Commerce a 3-day trial and then US$1 for the first month.

## Owner decisions so far (2026-11-10)

- **The opening special is 6 months.** Website and plugin readme already said 6. The user guide, the billing code (`BillingPricer.SpecialMonths`) and the platform pricing page said 12; PR iszolea/anybook#490 (draft) changes them to 6. Businesses that already signed up since go-live keep the 12-month end date they were sold with (Stripe metadata), unless the owner shortens them by hand.
- **"No trial for now."** Sign-up stays as it is, and the readme keeps "Is there a free trial? No". The readme leads with the demo once PBI 1.11 makes the demo customer pages work. **Revisit one month after the plugin's first installs (about 2026-12-10).**
- **A trial for WordPress sign-ups is expected later** ("we will need one on WordPress"). That points to option C below (or B/D if the owner later wants it for everyone). Nothing is built; the next step is the owner choosing length (14 or 30 days) and whether the card is taken at the start.

## The decisions

1. **Length:** none, 7, 14 or 30 days.
2. **Who:** everyone who signs up, or only people who sign up through the plugin.
3. **Card at the start or not.**
4. **How it fits the opening special:** the special's clock starts at the first payment (trial first) or at sign-up (the trial eats into it).

## Options

| | What the business gets | Effect on billing | For | Against |
|---|---|---|---|---|
| **A. No trial (today).** Add a prominent live demo and the price on the plugin page | Try the demo; pay US$10 at sign-up | Nothing changes. First charge at sign-up | Simplest; only serious businesses; the US$10 is already low | Looks stricter than the other hosted plugins; the plugin shows nothing until you pay |
| **B. 14 days free for everyone, card at sign-up** | 14 days, then the US$10 special starts. Cancel before day 14: pay nothing | Stripe Checkout with a trial period (or Anybook's trial). First invoice on day 14. The special's months start then. Needs a "trial ends" email and a failed-first-charge path | The familiar WordPress pattern; the card filters casual sign-ups | Some accounts never convert; the sign-up change touches the app, website, Terms and plugin page |
| **C. 14 days free, only from the plugin** | As B, only when the sign-up started from the plugin's Connect/sign-up link | As B, plus a way to tell the source (a parameter in the sign-up link the plugin already sends) | Something to say on WordPress.org without changing the main site | Two prices of entry to explain and test; non-plugin visitors who find out will ask for it. Also the plugin must not become a back door: the page that shows the offer must be Anybook's own sign-up |
| **D. 30 days free, card at sign-up** | As B, for a month | As B, but the special starts a month later | Enough time to set up and take real bookings (the sign-up checklist has about 8 steps) | A month of service and email per unpaid account; the 1,500 included emails and 40 help questions apply during the trial, so cost exposure is real but small |
| **E. First month US$1** (Twice Commerce does this) | US$1, then the US$10 special | A US$1 first charge, then the special. No trial logic needed | Proves the card at once; no trial code | Odd beside a price that is already US$10; a refund request is a support burden with no human help desk |

## What each choice changes in the product (so the owner knows the work)

| Change | A | B | C | D | E |
|---|---|---|---|---|---|
| Stripe Checkout / price | none | trial period on the subscription | same, only on the plugin path | same as B | a one-off first price |
| App sign-up and billing code | none | uses or extends the existing trial; first-invoice and trial-end handling | same, plus the source flag | same as B | small |
| "Trial ending" email | none | yes (Anybook's own banner; Stripe's trial email stays off) | yes | yes | no |
| Opening-special start and end date | unchanged | starts after the trial | same | same | starts after the US$1 month |
| Website, Terms, plugin readme, user guide | no change | all change on the same day | all change | all change | all change |
| Auto-approval (#322) | unchanged | decide: approve at card, or at first payment | same | same | unchanged |

## Recommendation

**A for now:** keep sign-up as it is, put the live demo (when ready) and the price at the top of the plugin page, and look at the numbers after the first month of plugin installs. The US$10 opening special already does most of what a trial does, and the owner wants only serious sign-ups (no unpaid accounts to chase, no contact with customers). If the owner wants to match the other hosted plugins, **B with 14 days and the card at sign-up** is the smallest change that matches what WordPress users expect, because the app already has trial code. **C** is only worth it if the owner wants the website to stay unchanged.

## Owner questions (one at a time, please)

1. For the WordPress trial: 14 or 30 days, and card at the start or not?
