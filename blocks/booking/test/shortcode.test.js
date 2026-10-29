/**
 * The transform from a Shortcode block: the same choices as the PHP shortcode (Shortcode::render()).
 */
import {
	attributesFromShortcode,
	isAnybookShortcode,
	parseShortcode,
} from '../shortcode';

describe( 'isAnybookShortcode', () => {
	it( 'matches [anybook] alone, with or without attributes', () => {
		expect( isAnybookShortcode( '[anybook]' ) ).toBe( true );
		expect( isAnybookShortcode( '  [anybook unit="belle"]\n' ) ).toBe(
			true
		);
	} );

	it( 'does not match other shortcodes or extra text', () => {
		expect( isAnybookShortcode( '[anybookx]' ) ).toBe( false );
		expect( isAnybookShortcode( '[gallery]' ) ).toBe( false );
		expect( isAnybookShortcode( 'Book: [anybook]' ) ).toBe( false );
		expect( isAnybookShortcode( '' ) ).toBe( false );
	} );
} );

describe( 'parseShortcode', () => {
	it( 'reads double, single and bare values', () => {
		expect(
			parseShortcode(
				'[anybook unit="belle" accent=\'#0b6e4f\' guest_links=yes]'
			)
		).toEqual( { unit: 'belle', accent: '#0b6e4f', guest_links: 'yes' } );
	} );
} );

describe( 'attributesFromShortcode', () => {
	it( 'shows all units when nothing is chosen', () => {
		expect( attributesFromShortcode( '[anybook]' ) ).toEqual( {
			show: 'all',
		} );
	} );

	it( 'works out what to show from a unit, a service or services="yes"', () => {
		expect( attributesFromShortcode( '[anybook unit="belle"]' ) ).toEqual( {
			show: 'unit',
			unit: 'belle',
		} );
		expect( attributesFromShortcode( '[anybook service="cut"]' ) ).toEqual(
			{ show: 'service', service: 'cut' }
		);
		expect(
			attributesFromShortcode( '[anybook services="yes"]' ).show
		).toBe( 'appointments' );
		// As in PHP: services="yes" wins over a unit when show is not given.
		expect(
			attributesFromShortcode( '[anybook services="yes" unit="belle"]' )
				.show
		).toBe( 'appointments' );
	} );

	it( 'keeps an explicit show, and ignores an unknown one', () => {
		expect(
			attributesFromShortcode( '[anybook show="Appointments"]' ).show
		).toBe( 'appointments' );
		expect(
			attributesFromShortcode( '[anybook show="nonsense" unit="belle"]' )
				.show
		).toBe( 'unit' );
	} );

	it( 'maps colours, wording and guest links', () => {
		expect(
			attributesFromShortcode(
				'[anybook accent="#0b6e4f" text="#1f2933" button_text="Book now" details_heading="About you" dates_text="Pick dates" guest_links="yes"]'
			)
		).toEqual( {
			show: 'all',
			accent: '#0b6e4f',
			text: '#1f2933',
			buttonText: 'Book now',
			detailsHeading: 'About you',
			datesText: 'Pick dates',
			guestLinks: true,
		} );
		expect(
			attributesFromShortcode( '[anybook guest_links="no"]' ).guestLinks
		).toBeUndefined();
	} );
} );
