/**
 * Turning an `[anybook ...]` shortcode into the block's attributes, for the transform from a Shortcode block
 * (review Q11d). The choices follow Renderer::options_for() in PHP: `show` wins, else a service, a unit or
 * `services="yes"` decide what is shown.
 */

/** The shortcode's wording and colour words, to the block's attribute names. */
const NAMES = {
	unit: 'unit',
	service: 'service',
	accent: 'accent',
	text: 'text',
	button_text: 'buttonText',
	details_heading: 'detailsHeading',
	dates_text: 'datesText',
};

const SHOW = [ 'all', 'unit', 'appointments', 'service' ];

/**
 * Whether some text is one `[anybook]` shortcode and nothing else.
 *
 * @param {string} text Shortcode block text.
 * @return {boolean} True for `[anybook ...]` alone.
 */
export function isAnybookShortcode( text ) {
	return /^\s*\[anybook(\s[^\]]*)?\]\s*$/.test( String( text || '' ) );
}

/**
 * The shortcode's attributes as written: `name="value"`, `name='value'` or `name=value`.
 *
 * @param {string} text Shortcode text.
 * @return {Object} Lower-case names to values.
 */
export function parseShortcode( text ) {
	const found = {};
	const inner = String( text || '' ).replace( /^\s*\[anybook|\]\s*$/g, '' );
	const pattern =
		/([A-Za-z_][\w-]*)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"']+))/g;
	let match;
	while ( ( match = pattern.exec( inner ) ) !== null ) {
		let value = match[ 4 ];
		if ( match[ 2 ] !== undefined ) {
			value = match[ 2 ];
		} else if ( match[ 3 ] !== undefined ) {
			value = match[ 3 ];
		}
		found[ match[ 1 ].toLowerCase() ] = value;
	}
	return found;
}

/**
 * The block's attributes for a shortcode.
 *
 * @param {string} text Shortcode text.
 * @return {Object} Block attributes.
 */
export function attributesFromShortcode( text ) {
	const words = parseShortcode( text );
	const yes = ( value ) =>
		[ 'yes', 'true', '1', 'on' ].includes(
			String( value || '' ).toLowerCase()
		);
	const attributes = {};
	Object.keys( NAMES ).forEach( ( name ) => {
		if ( words[ name ] ) {
			attributes[ NAMES[ name ] ] = words[ name ];
		}
	} );
	let show = String( words.show || '' )
		.trim()
		.toLowerCase();
	if ( ! show && yes( words.services ) && ! attributes.service ) {
		// services="yes" is the older way to say show="appointments" (Shortcode::render()).
		show = 'appointments';
	}
	if ( ! SHOW.includes( show ) ) {
		if ( attributes.service ) {
			show = 'service';
		} else if ( attributes.unit ) {
			show = 'unit';
		} else {
			show = 'all';
		}
	}
	attributes.show = show;
	if ( yes( words.guest_links ) ) {
		attributes.guestLinks = true;
	}
	return attributes;
}
