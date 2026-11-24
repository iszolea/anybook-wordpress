/**
 * The settings page's in-page "are you sure" for Disconnect (AB#988), the script exactly as includes/class-admin.php adds it.
 * The form is as WordPress draws it: submit_button() names the button "submit", which hides the form's own submit()
 * (Screencasts on 0.1.4: "Yes, disconnect" did nothing, "f.submit is not a function"). Kept here so the block's Jest run
 * covers it (this folder is left out of the release zip).
 */
/* global HTMLFormElement, KeyboardEvent */
const fs = require( 'fs' );
const path = require( 'path' );

function inlineScript() {
	const php = fs.readFileSync(
		path.join( __dirname, '../../../includes/class-admin.php' ),
		'utf8'
	);
	const found = php.match(
		/wp_add_inline_script\( self::ASSET_HANDLE, '((?:[^'\\]|\\.)*)' \);/
	);
	expect( found ).not.toBeNull();
	return found[ 1 ].replace( /\\'/g, "'" );
}

describe( 'Disconnect asks in the page', () => {
	let form, sent;

	beforeAll( () => {
		// Runs the script once, as the page does: one submit listener on the document.
		// eslint-disable-next-line no-new-func
		new Function( inlineScript() )();
	} );

	beforeEach( () => {
		document.body.innerHTML = `
			<form method="post" action="admin-post.php" data-anybook-confirm="Disconnect from Anybook? The booking widget stops showing on this website."
				data-anybook-yes="Yes, disconnect" data-anybook-no="Cancel">
				<input type="hidden" name="action" value="anybook_disconnect" />
				<input type="submit" name="submit" id="submit" class="button delete" value="Disconnect" />
			</form>`;
		form = document.querySelector( 'form' );
		// What a browser does and jsdom does not: a control named "submit" hides the form's own submit() method.
		Object.defineProperty( form, 'submit', {
			value: form.querySelector( '[name="submit"]' ),
		} );
		sent = 0;
		jest.spyOn( HTMLFormElement.prototype, 'submit' ).mockImplementation(
			function () {
				if ( this === form ) {
					sent++;
				}
			}
		);
	} );

	afterEach( () => jest.restoreAllMocks() );

	const askToDisconnect = () =>
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);

	it( 'is a form whose submit() is hidden by its button, as WordPress draws it', () => {
		expect( typeof form.submit ).not.toBe( 'function' );
	} );

	it( 'holds the first submit and asks in the page', () => {
		expect( askToDisconnect() ).toBe( false );
		const box = document.querySelector( '.anybook-confirm' );
		expect( box.getAttribute( 'role' ) ).toBe( 'alertdialog' );
		expect( box.textContent ).toContain( 'Disconnect from Anybook?' );
		expect( document.activeElement.textContent ).toBe( 'Yes, disconnect' );
		expect( sent ).toBe( 0 );
	} );

	it( '"Yes, disconnect" sends the form', () => {
		askToDisconnect();
		document.querySelector( '.anybook-confirm .button-primary' ).click();
		expect( sent ).toBe( 1 );
	} );

	it( 'Cancel and Esc send nothing', () => {
		askToDisconnect();
		document.querySelectorAll( '.anybook-confirm button' )[ 1 ].click();
		expect( document.querySelector( '.anybook-confirm' ) ).toBeNull();
		askToDisconnect();
		document
			.querySelector( '.anybook-confirm' )
			.dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } )
			);
		expect( document.querySelector( '.anybook-confirm' ) ).toBeNull();
		expect( sent ).toBe( 0 );
	} );
} );
