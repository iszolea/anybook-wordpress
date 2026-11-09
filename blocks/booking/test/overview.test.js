/**
 * The availability overview in the editor (PBI 30581): the "Availability overview" choice, its sidebar
 * controls and the preview words. WordPress's editor packages are replaced by small stand-ins that note the
 * props they were given, so a test can find a control by its label and call its onChange.
 */
jest.mock(
	'@wordpress/components',
	() => {
		const { createElement: h } = require( '@wordpress/element' );
		const part =
			( name ) =>
			( { children, ...props } ) => {
				global.anybookParts.push( { name, props } );
				return h(
					'div',
					{ 'data-part': name },
					props.label,
					props.instructions,
					children
				);
			};
		return {
			Button: part( 'Button' ),
			CheckboxControl: part( 'CheckboxControl' ),
			Notice: part( 'Notice' ),
			PanelBody: part( 'PanelBody' ),
			Placeholder: part( 'Placeholder' ),
			RangeControl: part( 'RangeControl' ),
			SelectControl: part( 'SelectControl' ),
			Spinner: part( 'Spinner' ),
			TextControl: part( 'TextControl' ),
			ToggleControl: part( 'ToggleControl' ),
		};
	},
	{ virtual: true }
);
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		InspectorControls: ( { children } ) => children,
		PanelColorSettings: ( { children, title } ) => {
			global.anybookParts.push( {
				name: 'PanelColorSettings',
				props: { title },
			} );
			return children;
		},
		useBlockProps: ( props ) => props,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );

const catalog = {
	units: [
		{ id: 'u1', code: 'belle', name: 'The Belle' },
		{ id: 'u2', code: 'kayak', name: 'Kayak' },
		{ id: 'u3', code: 'raft', name: 'Raft' },
	],
	services: [
		{
			id: '0f8fad5b-d9cb-469f-a165-70867728950e',
			code: 'cut',
			name: 'Haircut',
		},
	],
};

/**
 * The edit module and the React it uses, loaded after window.anybookBlock is set.
 *
 * @param {Object} site What Block::editor_data() would print.
 */
function loadEdit( site ) {
	window.anybookBlock = site;
	let loaded;
	jest.isolateModules( () => {
		loaded = {
			...require( '../edit' ),
			apiFetch: require( '@wordpress/api-fetch' ),
			element: require( '@wordpress/element' ),
			// eslint-disable-next-line import/no-extraneous-dependencies -- React comes with @wordpress/element.
			react: require( 'react' ),
		};
	} );
	return loaded;
}

/**
 * Renders a component, noting every stand-in control it drew, and returns its HTML and those controls.
 *
 * @param {Object}   loaded    loadEdit()'s result.
 * @param {Function} Component What to render.
 * @param {Object}   props     Its props.
 */
async function draw( loaded, Component, props ) {
	const { createElement, createRoot } = loaded.element;
	const { act } = loaded.react;
	global.IS_REACT_ACT_ENVIRONMENT = true;
	global.anybookParts = [];
	const container = document.createElement( 'div' );
	const root = createRoot( container );
	await act( async () => {
		root.render( createElement( Component, props ) );
	} );
	const html = container.innerHTML;
	const parts = global.anybookParts;
	act( () => root.unmount() );
	/**
	 * The last control drawn with this label (or title).
	 *
	 * @param {string} label Label.
	 */
	const find = ( label ) =>
		[ ...parts ]
			.reverse()
			.find(
				( part ) =>
					part.props.label === label || part.props.title === label
			);
	return { html, parts, find };
}

const DEFAULTS = {
	show: 'overview',
	weeks: 12,
	prices: false,
	units: '',
	rows: 'services',
	service: '',
};

describe( 'Edit with the availability overview', () => {
	it( 'offers "Availability overview" in What to show', async () => {
		const loaded = loadEdit( { connected: true } );
		loaded.apiFetch.mockResolvedValue( catalog );
		const { find } = await draw( loaded, loaded.default, {
			attributes: { show: 'all' },
			setAttributes: () => {},
		} );
		const options = find( 'Show' ).props.options;
		expect( options.map( ( option ) => option.value ) ).toEqual( [
			'all',
			'unit',
			'appointments',
			'service',
			'overview',
		] );
		expect( options[ 4 ].label ).toBe( 'Availability overview' );
		expect( find( 'Weeks shown' ) ).toBeUndefined();
	} );

	it( 'shows the overview controls with their defaults, and not the widget’s', async () => {
		const loaded = loadEdit( { connected: true } );
		loaded.apiFetch.mockResolvedValue( catalog );
		const { find, html } = await draw( loaded, loaded.default, {
			attributes: DEFAULTS,
			setAttributes: () => {},
		} );
		const weeks = find( 'Weeks shown' );
		expect( weeks.name ).toBe( 'RangeControl' );
		expect( weeks.props ).toMatchObject( { min: 2, max: 26, value: 12 } );
		expect( find( 'Show prices' ).name ).toBe( 'ToggleControl' );
		expect( find( 'Show prices' ).props.checked ).toBe( false );
		const rows = find( 'Appointment rows' );
		expect( rows.props.value ).toBe( 'services' );
		expect( rows.props.options ).toEqual( [
			{ label: 'One row per service', value: 'services' },
			{ label: 'One row per team member', value: 'people' },
		] );
		// One checkbox per unit, none ticked: all units.
		expect( find( 'The Belle' ).name ).toBe( 'CheckboxControl' );
		expect( find( 'Kayak' ).props.checked ).toBe( false );
		// No service chooser until rows are per team member.
		expect( find( 'Service' ) ).toBeUndefined();
		// The widget's colors, wording and guest links are not for the overview.
		expect( find( 'Colors' ) ).toBeUndefined();
		expect( find( 'Wording' ) ).toBeUndefined();
		expect( find( 'Guest links' ) ).toBeUndefined();
		expect( html ).toContain( 'Availability overview, 12 weeks' );
		expect( html ).not.toContain( 'Book and go to payment' );
	} );

	it( 'keeps weeks a whole number from 2 to 26, and turns prices on', async () => {
		const loaded = loadEdit( { connected: true } );
		loaded.apiFetch.mockResolvedValue( catalog );
		const setAttributes = jest.fn();
		const { find } = await draw( loaded, loaded.default, {
			attributes: DEFAULTS,
			setAttributes,
		} );
		const weeks = find( 'Weeks shown' ).props.onChange;
		weeks( 30 );
		weeks( 1 );
		weeks( 7.6 );
		weeks( undefined );
		expect( setAttributes.mock.calls.map( ( call ) => call[ 0 ] ) ).toEqual(
			[ { weeks: 26 }, { weeks: 2 }, { weeks: 8 }, { weeks: 12 } ]
		);
		setAttributes.mockClear();
		find( 'Show prices' ).props.onChange( true );
		expect( setAttributes ).toHaveBeenCalledWith( { prices: true } );
	} );

	it( 'ticks units into a comma-separated list of codes, in catalogue order', async () => {
		const loaded = loadEdit( { connected: true } );
		loaded.apiFetch.mockResolvedValue( catalog );
		const setAttributes = jest.fn();
		const { find } = await draw( loaded, loaded.default, {
			attributes: { ...DEFAULTS, units: 'raft' },
			setAttributes,
		} );
		expect( find( 'Raft' ).props.checked ).toBe( true );
		find( 'The Belle' ).props.onChange( true );
		expect( setAttributes ).toHaveBeenLastCalledWith( {
			units: 'belle,raft',
		} );
		find( 'Raft' ).props.onChange( false );
		expect( setAttributes ).toHaveBeenLastCalledWith( { units: '' } );
	} );

	it( 'offers a service chooser by id for one row per team member', async () => {
		const loaded = loadEdit( { connected: true } );
		loaded.apiFetch.mockResolvedValue( catalog );
		const setAttributes = jest.fn();
		const { find, html } = await draw( loaded, loaded.default, {
			attributes: { ...DEFAULTS, rows: 'people' },
			setAttributes,
		} );
		const service = find( 'Service' );
		expect( service.name ).toBe( 'SelectControl' );
		expect( service.props.options ).toEqual( [
			{ label: 'Choose…', value: '' },
			{ label: 'Haircut', value: '0f8fad5b-d9cb-469f-a165-70867728950e' },
		] );
		expect( html ).toContain( 'choose a service' );
		find( 'Appointment rows' ).props.onChange( 'services' );
		expect( setAttributes ).toHaveBeenCalledWith( { rows: 'services' } );
	} );

	it( 'falls back to a box for unit codes when the list could not be had', async () => {
		const loaded = loadEdit( { connected: true } );
		loaded.apiFetch.mockRejectedValue( new Error( 'Offline' ) );
		const setAttributes = jest.fn();
		const { find, html } = await draw( loaded, loaded.default, {
			attributes: DEFAULTS,
			setAttributes,
		} );
		expect( html ).toContain( 'Offline' );
		const box = find( 'Units' );
		expect( box.name ).toBe( 'TextControl' );
		box.props.onChange( 'Belle, kayak;"><b>' );
		expect( setAttributes ).toHaveBeenCalledWith( {
			units: 'belle,kayakb',
		} );
	} );
} );

describe( 'the overview preview words', () => {
	const { describe: describeChoice } = loadEdit( { connected: true } );

	it( 'says how many weeks, the units and the rows', () => {
		expect( describeChoice( DEFAULTS, catalog ) ).toBe(
			'Availability overview, 12 weeks'
		);
		expect(
			describeChoice(
				{ ...DEFAULTS, weeks: 4, prices: true, units: 'belle,kayak' },
				catalog
			)
		).toBe(
			'Availability overview, 4 weeks, with prices: The Belle, Kayak'
		);
		expect(
			describeChoice(
				{
					...DEFAULTS,
					rows: 'people',
					service: '0f8fad5b-d9cb-469f-a165-70867728950e',
				},
				catalog
			)
		).toBe( 'Availability overview, 12 weeks: team members for Haircut' );
		expect( describeChoice( { show: 'overview' }, catalog ) ).toBe(
			'Availability overview, 12 weeks'
		);
	} );
} );
