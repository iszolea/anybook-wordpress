/**
 * Draws the plugin directory's banner and icon in Anybook's brand (the same mark and colours as anybookapp.com:
 * a mint chevron on a navy rounded square, public/favicon.svg in anybook-website):
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
const NAVY = '#0b1b3a'; // the website's logo square
const MINT = '#36e2b4'; // the website's chevron

/** The Anybook mark (the website's favicon.svg), `size` pixels wide. */
const mark = ( size ) => `
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="${ size }" height="${ size }">
		<rect width="32" height="32" rx="8" fill="${ NAVY }"/>
		<path d="M9 22 16 8l7 14" stroke="${ MINT }" stroke-width="3.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
	</svg>`;

const banner = ( w, h ) => `
	<body style="margin:0;width:${ w }px;height:${ h }px;display:flex;align-items:center;gap:${ h * 0.1 }px;padding:0 ${ h * 0.2 }px;box-sizing:border-box;
		background:${ NAVY };font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#fff">
		<div style="flex:none;line-height:0;border-radius:${ h * 0.1 }px;box-shadow:0 0 0 ${ Math.max( 1, h * 0.006 ) }px rgba(54,226,180,.4)">${ mark( h * 0.4 ) }</div>
		<div>
			<div style="font-size:${ h * 0.17 }px;font-weight:800;letter-spacing:-.03em;line-height:1.05">Anybook</div>
			<div style="font-size:${ h * 0.072 }px;margin-top:${ h * 0.035 }px;color:${ MINT };font-weight:600">Online booking and card payments for your WordPress site</div>
		</div>
	</body>`;

const icon = ( s ) => `
	<body style="margin:0;width:${ s }px;height:${ s }px;line-height:0;background:${ NAVY }">${ mark( s ) }</body>`;

mkdirSync( OUT, { recursive: true } );
// PW_CHANNEL=chrome uses the installed Chrome when Playwright's own browser is not downloaded.
const browser = await chromium.launch( process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {} );
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
