/**
 * The browser half of tests/e2e/run.sh (run it from there: it sets the addresses and test logins).
 *
 *   node tests/e2e/browser.mjs connect          connect as the Owner through Anybook, the status, the editor
 *   node tests/e2e/browser.mjs visit <plugin>   the booking page as a visitor: the widget renders
 *
 * Playwright and its Chromium come with this repo's node_modules (@wordpress/scripts brings them).
 */
import { chromium } from 'playwright';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const { WP_URL, WP_USER, WP_PASS, ANYBOOK_URL, OWNER_EMAIL, OWNER_PASSWORD } =
	process.env;
const STATE = process.env.E2E_STATE || join( ROOT, 'tests', 'e2e', '.state.json' );
const SHOTS = join( ROOT, '.wordpress-org' );
const ARTIFACTS = join( ROOT, 'tests', 'e2e', 'artifacts' );
const [ mode, label = '' ] = process.argv.slice( 2 );

function check( ok, what ) {
	if ( ! ok ) {
		throw new Error( what );
	}
}

const browser = await chromium.launch( {
	headless: true,
	// As booking-system's click-through tests: both sites are on this computer, which Chrome would otherwise guard.
	args: [
		'--disable-features=LocalNetworkAccessChecks,BlockInsecurePrivateNetworkRequests,PrivateNetworkAccessSendPreflights',
	],
} );
let current;
try {
	if ( mode === 'connect' ) {
		await connect();
	} else if ( mode === 'visit' ) {
		await visit( label );
	} else {
		throw new Error( 'mode: connect or visit <plugin>' );
	}
} catch ( error ) {
	mkdirSync( ARTIFACTS, { recursive: true } );
	if ( current ) {
		await current
			.screenshot( { path: join( ARTIFACTS, `${ mode }-${ label }-failed.png` ), fullPage: true } )
			.catch( () => {} );
		console.error( 'at', current.url() );
	}
	console.error( 'FAIL:', error.message );
	process.exitCode = 1;
} finally {
	await browser.close();
}

async function connect() {
	const context = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	context.setDefaultTimeout( 60000 );
	const page = ( current = await context.newPage() );
	const visitor = await browser.newContext();

	// WordPress, as its administrator.
	await page.goto( `${ WP_URL }/wp-login.php` );
	await page.fill( '#user_login', WP_USER );
	await page.fill( '#user_pass', WP_PASS );
	await Promise.all( [ page.waitForURL( /\/wp-admin\// ), page.click( '#wp-submit' ) ] );
	await page.goto( `${ WP_URL }/wp-admin/admin.php?page=anybook` );
	check( await page.locator( 'input[name=anybook_make_page]' ).isChecked(), '"Make a booking page" is not ticked' );
	check( ( await page.locator( 'body' ).textContent() ).includes( 'Set by ANYBOOK_SITE in wp-config.php' ), 'ANYBOOK_SITE not shown' );
	await page.getByRole( 'button', { name: 'Connect to Anybook', exact: true } ).click();

	// Anybook: sign in as the Owner, then the connect page.
	await page.waitForURL( ( url ) => url.href.startsWith( ANYBOOK_URL ) );
	if ( page.url().includes( '/Account/Login' ) ) {
		await page.fill( '#Input\\.Email', OWNER_EMAIL );
		await page.fill( '#Input\\.Password', OWNER_PASSWORD );
		await page.getByRole( 'button', { name: 'Log in', exact: true } ).click();
	}
	await page.locator( '#connect-origin' ).waitFor();
	check( ( await page.locator( '#connect-origin' ).innerText() ).trim() === WP_URL, 'the connect page shows another website' );

	// While the Owner is on Anybook, the booking page is a draft that visitors cannot see (review Q1).
	const admin = await context.newPage();
	await admin.goto( `${ WP_URL }/wp-admin/edit.php?post_type=page&post_status=draft` );
	const row = admin.locator( 'tr', { hasText: 'Book online' } ).first();
	const pageId = ( ( await row.getAttribute( 'id' ) ) || '' ).replace( 'post-', '' );
	check( /^\d+$/.test( pageId ), 'no draft "Book online" page while the Owner is on Anybook' );
	await admin.close();
	check( ( await visitor.request.get( `${ WP_URL }/?page_id=${ pageId }` ) ).status() === 404, 'the draft is visible to visitors' );

	// Allow, with the guest page ticked (AC-2).
	const box = page.locator( '#connect-guest-page-box, #connect-replace-box' );
	check( ( await box.count() ) > 0, 'Anybook offers no guest page choice' );
	await box.first().check();
	await page.locator( '#connect-allow' ).click();
	await page.waitForURL( ( url ) => url.href.startsWith( `${ WP_URL }/wp-admin/admin.php?page=anybook` ) );
	await page.getByText( 'Connected to Anybook.', { exact: false } ).first().waitFor();
	const status = await page.locator( '.anybook-status' ).innerText();
	for ( const line of [ 'Connected to E2E Houseboats', 'approved on Anybook', 'Online booking is on', `This website (${ WP_URL }) is on your allowed websites`, 'is on this website' ] ) {
		check( status.includes( line ), `status lacks "${ line }": ${ status }` );
	}
	check( ! page.url().includes( 'state=' ), 'the return values stayed in the address' );
	mkdirSync( SHOTS, { recursive: true } );
	await page.screenshot( { path: join( SHOTS, 'screenshot-1.png' ) } );

	// Published now, with the widget.
	const published = await visitor.request.get( `${ WP_URL }/?page_id=${ pageId }` );
	check( published.status() === 200 && ( await published.text() ).includes( '<booking-widget' ), 'the booking page was not published with the widget' );

	// Anybook's real site-status answer: the shared fixture the unit tests read (review Q6).
	const slug = ( status.match( /business code ([A-Za-z0-9_-]+)/ ) || [] )[ 1 ];
	const site = await visitor.request.get( `${ ANYBOOK_URL }/api/public/v1/${ slug }/site?origin=${ encodeURIComponent( WP_URL ) }` );
	check( site.ok(), `/site answered ${ site.status() }` );
	const fixture = await site.json();
	check( fixture.originAllowed === true && fixture.guestPage === 'this-site', `/site: ${ JSON.stringify( fixture ) }` );
	writeFileSync( join( ROOT, 'tests', 'fixtures', 'site-status.json' ), JSON.stringify( fixture, null, '\t' ) + '\n' );

	// The editor: the catalogue from Anybook, and the block's preview for one unit.
	await page.goto( `${ WP_URL }/wp-admin/post-new.php?post_type=page` );
	await page.waitForFunction( () => window.wp?.data?.select( 'core/block-editor' ) && window.wp?.blocks?.getBlockType( 'anybook/booking' ) );
	await page.evaluate( () => window.wp.data.dispatch( 'core/preferences' )?.set( 'core/edit-post', 'welcomeGuide', false ) );
	await page.keyboard.press( 'Escape' );
	const catalog = await page.evaluate( () => window.wp.apiFetch( { path: '/anybook/v1/catalog' } ) );
	check( catalog.units.length > 0, 'the catalogue lists no units' );
	const unit = catalog.units[ 0 ];
	await page.evaluate( ( code ) => {
		const { dispatch } = window.wp.data;
		const block = window.wp.blocks.createBlock( 'anybook/booking', { show: 'unit', unit: code } );
		dispatch( 'core/editor' ).editPost( { title: 'Book the boat' } );
		dispatch( 'core/block-editor' ).insertBlocks( block );
		dispatch( 'core/block-editor' ).selectBlock( block.clientId );
		dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
	}, unit.code );
	const card = page.frameLocator( 'iframe[name="editor-canvas"]' ).locator( '.anybook-booking-preview__card' );
	await card.waitFor();
	check( ( await card.innerText() ).includes( `Booking for ${ unit.name }` ), `preview: ${ await card.innerText() }` );
	const chooser = page.getByLabel( 'Unit', { exact: true } );
	await chooser.waitFor();
	check( ( await chooser.inputValue() ) === unit.code, 'the unit chooser does not show the unit' );
	await page.screenshot( { path: join( SHOTS, 'screenshot-2.png' ) } );

	writeFileSync( STATE, JSON.stringify( { pageId, slug, unit: unit.name } ) );
	console.log( `  connected: business ${ slug }, booking page ${ pageId }, catalogue unit ${ unit.code }` );
}

async function visit( plugin ) {
	const { pageId, unit } = JSON.parse( readFileSync( STATE, 'utf8' ) );
	const context = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	const page = ( current = await context.newPage() );
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.message ) );
	await page.goto( `${ WP_URL }/?page_id=${ pageId }` );

	const scripts = await page.evaluate( () => [ ...document.querySelectorAll( 'script[src]' ) ].map( ( s ) => ( { src: s.src, head: s.parentElement === document.head } ) ) );
	const widget = scripts.filter( ( s ) => s.src.includes( 'booking-widget.js' ) );
	check( widget.length === 1 && widget[ 0 ].src === `${ ANYBOOK_URL }/widget/booking-widget.js` && widget[ 0 ].head, `widget script: ${ JSON.stringify( widget ) }` );
	const marker = { autoptimize: 'autoptimize', 'litespeed-cache': 'litespeed' }[ plugin ];
	if ( marker ) {
		check( scripts.some( ( s ) => s.src.includes( marker ) ), `${ plugin } did not optimise the page's scripts` );
	}

	// The widget really runs: its shadow root fills with the unit's calendar from Anybook, with nightly prices.
	await page.waitForFunction(
		() => {
			const text = document.querySelector( 'booking-widget' )?.shadowRoot?.textContent || '';
			return text.includes( 'Available' ) && /\$\d/.test( text );
		},
		null,
		{ timeout: 30000 }
	);
	const widgetErrors = errors.filter( ( e ) => /booking|anybook|widget/i.test( e ) );
	check( widgetErrors.length === 0, `widget errors: ${ widgetErrors.join( '; ' ) }` );
	if ( plugin === 'none' ) {
		await page.screenshot( { path: join( SHOTS, 'screenshot-3.png' ) } );
	}
	console.log( `ok - visitor (${ plugin }): the widget renders the calendar of "${ unit }"${ errors.length ? `; other page errors: ${ errors.join( '; ' ) }` : '' }` );
}
