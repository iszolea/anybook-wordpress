# Anybook Online Booking (WordPress plugin)

The WordPress plugin that puts the Anybook booking widget on a business's website. It is a thin connector: the
widget and every booking, price and payment stay on Anybook (app.anybookapp.com). Design: PBI 30564 in
`Halo/booking-system-artifacts` (`30564/2_decide/`).

Licence: GPLv2 or later (see `LICENSE`). This repository holds only the plugin's own code; it never copies
booking-system code.

Branches: `develop` (changed only through pull requests) and `feature/pbi-<id>-<name>`.

## Checks
Docker Desktop is all the PC needs for these (no PHP or Node):

- `bash tools/docker-test.sh`: PHP lint on 7.4 and 8.3, PHPUnit unit tests (Brain Monkey) on both, PHPCS with the
  WordPress Coding Standards and PHP 7.4 compatibility.
- `bash tools/docker-js.sh`: `npm ci`, JS and style lint, the Jest tests of the block, and a fresh build compared
  with the committed `build/`. `WRITE=1` writes the fresh build into `build/` (after changing `blocks/`).
- `bash tests/smoke/smoke.sh`: the committed tree in a real WordPress (latest image) with Anybook faked: connect
  return, the booking page a draft until the Owner agrees, settings page, script loading on a block theme and a
  classic theme, Elementor-style content, Autoptimize and LiteSpeed Cache with script optimisations on, Plugin
  Check (must run, no errors, no warnings beyond its allow-list), readme "Tested up to", multisite (shared-address
  warning, uninstall). `KEEP=1` keeps the containers for a look; otherwise they are removed.
- `BOOKING_SYSTEM=<booking-system working copy> bash tests/e2e/run.sh`: end to end against the real Anybook (its
  Docker image on port 8092, WordPress on 8093): connect as the Owner through Anybook's connect page, the status,
  the editor's block and catalogue, the widget rendering for a visitor with Autoptimize and LiteSpeed Cache on, the
  Owners' email. It also saves Anybook's real `/site` answer to `tests/fixtures/site-status.json` (the unit tests
  read it) and the directory screenshots to `.wordpress-org/`. Needs .NET and Node with `node_modules` as well.
- Translations template: `wp i18n make-pot . languages/anybook.pot --slug=anybook --exclude=vendor,node_modules,tests,tools,blocks/booking/test`.

The block's source is `blocks/booking/`; `npm install` then `npm run build` writes `build/booking/`, which is
committed so the release tree (`git archive`) holds the built block. `package.json` ships in the release so the
build steps are with the source. TypeScript is pinned to 5.x (`overrides`) because the lint tools do not run with
TypeScript 7.

Another booking site (a test install of Anybook): `define( 'ANYBOOK_SITE', 'https://...' );` in wp-config.php. The
settings page accepts only anybookapp.com addresses or local ones, and only from users with `unfiltered_html`.

## Release (owner's go-ahead only)
- `bash tools/release.sh --zip`: `dist/anybook-<version>.zip`, the tree `git archive` packs, after checking the
  versions agree and `build/` matches its source. The owner uploads it once at
  https://wordpress.org/plugins/developers/add/ for the first review.
- `bash tools/release.sh --submit --go-ahead "<who, when>"`: after approval, commits trunk, the version tag and the
  images in `.wordpress-org/` to https://plugins.svn.wordpress.org/anybook, with the SVN password read from Windmill
  (`f/anybook/wordpress_org_svn_password`). It refuses without `--submit`, and while `.wordpress-org/DRAFT.txt` says
  the images are not approved.
- `node tools/draft-assets.mjs` redraws the draft banner and icon.
