/**
 * Draws the plugin directory's banner and icon as plain drafts (design Step 7), for the owner to approve or replace:
 * .wordpress-org/banner-1544x500.png, banner-772x250.png, icon-256x256.png and icon-128x128.png. The screenshots
 * (screenshot-1 to 3) come from tests/e2e/run.sh, which takes them from the real plugin.
 *
 *   node tools/draft-assets.mjs
 *
 * Plain shapes and system fonts only, no outside images. Uses Playwright's Chromium from node_modules.
 */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = join( dirname( fileURLToPath( import.meta.url ) ), '..', '.wordpress-org' );
const GREEN = '#0b6e4f';
const INK = '#1f2933';

/** A calendar with a tick, drawn in CSS, `size` pixels wide. */
const calendar = ( size, colour, paper ) => `
	<div style="position:relative;width:${ size }px;height:${ size * 0.92 }px;border-radius:${ size * 0.12 }px;background:${ paper };overflow:hidden;box-shadow:0 ${ size * 0.04 }px ${ size * 0.1 }px rgba(0,0,0,.18)">
		<div style="height:${ size * 0.26 }px;background:${ colour };opacity:.9"></div>
		<div style="position:absolute;left:${ size * 0.2 }px;top:${ -size * 0.06 }px;width:${ size * 0.1 }px;height:${ size * 0.2 }px;border-radius:${ size * 0.05 }px;background:${ INK }"></div>
		<div style="position:absolute;right:${ size * 0.2 }px;top:${ -size * 0.06 }px;width:${ size * 0.1 }px;height:${ size * 0.2 }px;border-radius:${ size * 0.05 }px;background:${ INK }"></div>
		<div style="position:absolute;left:${ size * 0.37 }px;top:${ size * 0.3 }px;width:${ size * 0.17 }px;height:${ size * 0.32 }px;border-right:${ size * 0.08 }px solid ${ colour };border-bottom:${ size * 0.08 }px solid ${ colour };transform:rotate(45deg);transform-origin:center"></div>
	</div>`;

const banner = ( w, h ) => `
	<body style="margin:0;width:${ w }px;height:${ h }px;display:flex;align-items:center;gap:${ h * 0.12 }px;padding:0 ${ h * 0.2 }px;box-sizing:border-box;
		background:linear-gradient(120deg, ${ GREEN } 0%, #0e8a63 100%);font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#fff">
		${ calendar( h * 0.46, GREEN, '#fff' ) }
		<div>
			<div style="font-size:${ h * 0.15 }px;font-weight:700;letter-spacing:-.01em">Anybook Online Booking</div>
			<div style="font-size:${ h * 0.075 }px;opacity:.92;margin-top:${ h * 0.03 }px">Bookings and card payments on your WordPress site</div>
		</div>
	</body>`;

const icon = ( s ) => `
	<body style="margin:0;width:${ s }px;height:${ s }px;display:flex;align-items:center;justify-content:center;background:${ GREEN }">
		${ calendar( s * 0.62, GREEN, '#fff' ) }
	</body>`;

mkdirSync( OUT, { recursive: true } );
const browser = await chromium.launch();
try {
	const page = await browser.newPage();
	const draw = async ( html, w, h, name ) => {
		await page.setViewportSize( { width: w, height: h } );
		await page.setContent( `<!doctype html><html>${ html }</html>` );
		await page.screenshot( { path: join( OUT, name ) } );
		console.log( 'drew', name );
	};
	await draw( banner( 1544, 500 ), 1544, 500, 'banner-1544x500.png' );
	await draw( banner( 772, 250 ), 772, 250, 'banner-772x250.png' );
	await draw( icon( 256 ), 256, 256, 'icon-256x256.png' );
	await draw( icon( 128 ), 128, 128, 'icon-128x128.png' );
} finally {
	await browser.close();
}
