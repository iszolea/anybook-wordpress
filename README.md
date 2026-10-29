# Anybook Online Booking (WordPress plugin)

The WordPress plugin that puts the Anybook booking widget on a business's website. It is a thin connector: the
widget and every booking, price and payment stay on Anybook (app.anybookapp.com). Design: PBI 30564 in
`Halo/booking-system-artifacts` (`30564/2_decide/`).

Licence: GPLv2 or later (see `LICENSE`). This repository holds only the plugin's own code; it never copies
booking-system code.

Branches: `develop` (changed only through pull requests) and `feature/pbi-<id>-<name>`.

## Checks
Docker Desktop is all the PC needs (no PHP):

- `bash tools/docker-test.sh`: PHP lint on 7.4 and 8.3, PHPUnit unit tests (Brain Monkey) on both, PHPCS with the
  WordPress Coding Standards and PHP 7.4 compatibility.
- `bash tests/smoke/smoke.sh`: the committed tree in a real WordPress (latest image) with Anybook faked: connect
  return, settings page, booking page and script loading, Plugin Check, multisite uninstall. `KEEP=1` keeps the
  containers for a look; otherwise they are removed.
- Translations template: `wp i18n make-pot . languages/anybook.pot --exclude=vendor,node_modules,tests,tools`.

The block's source is `blocks/booking/`; `npm install` then `npm run build` writes `build/booking/`, which is committed so the release tree (`git archive`) holds the built block. `npm run lint:js` and `npm run lint:style` check it. TypeScript is pinned to 5.x (`overrides`) because the lint tools do not run with TypeScript 7.
