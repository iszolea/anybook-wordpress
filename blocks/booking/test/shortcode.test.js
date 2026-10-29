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

describe( 'attributesFromShortcode for the availability overview', () => {
	it( 'keeps show="overview" and maps its choices', () => {
		expect(
			attributesFromShortcode(
				'[anybook show="overview" weeks="8" prices="yes" units="belle,kayak" rows="people" service="0f8fad5b-d9cb-469f-a165-70867728950e"]'
			)
		).toEqual( {
			show: 'overview',
			weeks: 8,
			prices: true,
			units: 'belle,kayak',
			rows: 'people',
			service: '0f8fad5b-d9cb-469f-a165-70867728950e',
		} );
		expect(
			attributesFromShortcode( '[anybook show="Overview"]' )
		).toEqual( { show: 'overview' } );
	} );

	it( 'keeps weeks a whole number from 2 to 26', () => {
		const weeks = ( typed ) =>
			attributesFromShortcode(
				`[anybook show="overview" weeks="${ typed }"]`
			).weeks;
		expect( weeks( '99' ) ).toBe( 26 );
		expect( weeks( '1' ) ).toBe( 2 );
		expect( weeks( ' 4 ' ) ).toBe( 4 );
		expect( weeks( 'x' ) ).toBeUndefined();
		expect( weeks( '-5' ) ).toBeUndefined();
		expect( weeks( '3.5' ) ).toBeUndefined();
	} );

	it( 'shows prices only for prices="yes"', () => {
		const prices = ( typed ) =>
			attributesFromShortcode(
				`[anybook show="overview" prices="${ typed }"]`
			).prices;
		expect( prices( 'yes' ) ).toBe( true );
		expect( prices( 'YES' ) ).toBe( true );
		expect( prices( 'true' ) ).toBeUndefined();
		expect( prices( '1' ) ).toBeUndefined();
	} );

	it( 'drops bad unit codes and rows other than people', () => {
		expect(
			attributesFromShortcode(
				"[anybook show='overview' units='belle,<script>,kayak_2,Kayak, raft ,belle' rows='staff']"
			)
		).toEqual( { show: 'overview', units: 'belle,raft' } );
		expect(
			attributesFromShortcode(
				'[anybook show="overview" units="<b>" rows="People"]'
			)
		).toEqual( { show: 'overview', rows: 'people' } );
	} );

	it( 'leaves the overview choices out of the widget', () => {
		expect(
			attributesFromShortcode(
				'[anybook unit="belle" weeks="8" prices="yes" rows="people"]'
			)
		).toEqual( { show: 'unit', unit: 'belle' } );
	} );
} );
