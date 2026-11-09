/**
 * Draws a PROPOSED banner (the approved one in .wordpress-org/ is not touched): the Anybook mark and a sales line on the
 * left, a drawn booking widget on a phone on the right. It is an illustration made of plain shapes, not a screenshot.
 * Writes .wordpress-org/proposed/banner-1544x500.png and banner-772x250.png. If the owner approves, copy them up one folder.
 *
 *   PW_CHANNEL=chrome node tools/draw-banner-proposal.mjs
 */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = join( dirname( fileURLToPath( import.meta.url ) ), '..', '.wordpress-org', 'proposed' );
const NAVY = '#0b1b3a';
const MINT = '#36e2b4';

const mark = ( s ) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="${ s }" height="${ s }"><rect width="32" height="32" rx="8" fill="${ NAVY }"/><path d="M9 22 16 8l7 14" stroke="${ MINT }" stroke-width="3.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

// A drawn booking widget: a month, a few days, free / booked, one selected range, a price and a button.
const phone = ( h ) => {
	const u = h / 100; // 1 unit = 1% of the banner height
	const days = Array.from( { length: 28 }, ( _, i ) => i + 1 );
	const cell = ( d ) => {
		const sel = d >= 12 && d <= 14;
		const booked = [ 3, 4, 5, 19, 20, 21, 22 ].includes( d );
		const bg = sel ? MINT : 'transparent';
		const col = sel ? NAVY : booked ? '#b8c0cc' : '#1b2a41';
		return `<div style="height:${ 4.6 * u }px;border-radius:${ 1.4 * u }px;background:${ bg };color:${ col };display:flex;align-items:center;justify-content:center;font-size:${ 2.5 * u }px;font-weight:${ sel ? 800 : 500 };${ booked ? 'text-decoration:line-through;' : '' }">${ d }</div>`;
	};
	return `<div style="width:${ 38 * u }px;height:${ 112 * u }px;background:#fff;border-radius:${ 5 * u }px;border:${ 1 * u }px solid #e4e9f0;box-shadow:0 ${ 2 * u }px ${ 8 * u }px rgba(0,0,0,.35);padding:${ 3.4 * u }px;box-sizing:border-box;color:#1b2a41;font-family:inherit">
		<div style="font-weight:800;font-size:${ 3.2 * u }px">Bluebird houseboat</div>
		<div style="font-size:${ 2.4 * u }px;color:#5b6778;margin:${ .6 * u }px 0 ${ 2 * u }px">from $425 / night</div>
		<div style="font-size:${ 2.6 * u }px;font-weight:700;margin-bottom:${ 1.2 * u }px">December</div>
		<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:${ .4 * u }px">${ days.map( cell ).join( '' ) }</div>
		<div style="margin-top:${ 2.4 * u }px;font-size:${ 2.4 * u }px;color:#5b6778">3 nights</div>
		<div style="font-size:${ 4 * u }px;font-weight:800">$1,266</div>
		<div style="font-size:${ 2.1 * u }px;color:#5b6778">$300 deposit today</div>
		<div style="margin-top:${ 2 * u }px;background:${ NAVY };color:#fff;text-align:center;border-radius:${ 1.8 * u }px;padding:${ 2.2 * u }px 0;font-weight:700;font-size:${ 2.8 * u }px">Book now</div>
	</div>`;
};

const banner = ( w, h ) => `
	<body style="margin:0;width:${ w }px;height:${ h }px;overflow:hidden;position:relative;background:${ NAVY };font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#fff">
		<div style="position:absolute;left:${ h * 0.16 }px;top:${ h * 0.14 }px;display:flex;align-items:center;gap:${ h * 0.04 }px">
			<div style="line-height:0;border-radius:${ h * 0.05 }px;box-shadow:0 0 0 ${ Math.max( 1, h * 0.005 ) }px rgba(54,226,180,.45)">${ mark( h * 0.13 ) }</div>
			<div style="font-size:${ h * 0.085 }px;font-weight:800;letter-spacing:-.02em">Anybook</div>
		</div>
		<div style="position:absolute;left:${ h * 0.16 }px;top:${ h * 0.34 }px;width:${ w * 0.58 }px">
			<div style="font-size:${ h * 0.125 }px;font-weight:800;letter-spacing:-.03em;line-height:1.08">Nights, days, hours, seats and appointments.</div>
			<div style="font-size:${ h * 0.066 }px;margin-top:${ h * 0.05 }px;color:${ MINT };font-weight:600;line-height:1.3">Guests book and pay on your own website. Flat price, no commission.</div>
		</div>
		<div style="position:absolute;right:${ w * 0.09 }px;top:${ h * 0.11 }px;transform:rotate(4deg)">${ phone( h ) }</div>
	</body>`;

mkdirSync( OUT, { recursive: true } );
const browser = await chromium.launch( process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {} );
try {
	const page = await browser.newPage();
	for ( const [ w, h ] of [ [ 1544, 500 ], [ 772, 250 ] ] ) {
		await page.setViewportSize( { width: w, height: h } );
		await page.setContent( `<!doctype html><html>${ banner( w, h ) }</html>` );
		await page.screenshot( { path: join( OUT, `banner-${ w }x${ h }.png` ) } );
		console.log( 'drew', w, h );
	}
} finally {
	await browser.close();
}
