/**
 * The editor's chooser, preview words and not-connected placeholder (review Q6). WordPress's editor packages are
 * replaced by small stand-ins, so the test needs only React.
 */
jest.mock(
	'@wordpress/components',
	() => {
		const { createElement: h } = require( '@wordpress/element' );
		const part =
			( name ) =>
			( { children, ...props } ) =>
				h(
					'div',
					{
						'data-part': name,
						'data-href': props.href,
						'data-target': props.target,
					},
					props.label,
					props.instructions,
					children
				);
		return {
			Button: part( 'Button' ),
			Notice: part( 'Notice' ),
			PanelBody: part( 'PanelBody' ),
			Placeholder: part( 'Placeholder' ),
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
		PanelColorSettings: ( { children } ) => children,
		useBlockProps: ( props ) => props,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );

/**
 * The edit module, loaded after window.anybookBlock is set (it reads it once), with the React it uses: each
 * load has its own module registry, so the test renders with that same copy.
 *
 * @param {Object} site What Block::editor_data() would print.
 */
function loadEdit( site ) {
	window.anybookBlock = site;
	let loaded;
	jest.isolateModules( () => {
		loaded = {
			...require( '../edit' ),
			components: require( '@wordpress/components' ),
			element: require( '@wordpress/element' ),
			// eslint-disable-next-line import/no-extraneous-dependencies -- React comes with @wordpress/element.
			react: require( 'react' ),
		};
	} );
	return loaded;
}

const catalog = {
	units: [ { id: 'u1', code: 'belle', name: 'The Belle' } ],
	services: [ { id: 's1', code: 'cut', name: 'Haircut' } ],
};

describe( 'CodeChooser', () => {
	const { CodeChooser, components } = loadEdit( { connected: true } );
	const base = {
		label: 'Unit',
		value: '',
		items: catalog.units,
		loading: false,
		failed: false,
		empty: 'No units',
		onChange: () => {},
	};

	it( 'shows a spinner while loading', () => {
		expect( CodeChooser( { ...base, loading: true } ).type ).toBe(
			components.Spinner
		);
	} );

	it( 'falls back to a code box that keeps only code characters', () => {
		const onChange = jest.fn();
		const box = CodeChooser( { ...base, failed: true, onChange } );
		expect( box.type ).toBe( components.TextControl );
		box.props.onChange( 'be lle<b>!' );
		expect( onChange ).toHaveBeenCalledWith( 'belleb' );
	} );

	it( 'says so when the business has none', () => {
		const empty = CodeChooser( { ...base, items: [] } );
		expect( empty.type ).toBe( 'p' );
		expect( empty.props.children ).toBe( 'No units' );
	} );

	it( 'lists the choices and keeps one no longer offered', () => {
		const select = CodeChooser( { ...base, value: 'gone' } );
		expect( select.type ).toBe( components.SelectControl );
		const labels = select.props.options.map( ( option ) => option.label );
		expect( labels ).toEqual( [
			'Choose…',
			'The Belle',
			'gone (no longer offered)',
		] );
	} );
} );

describe( 'the preview card words', () => {
	const { describe: describeChoice } = loadEdit( { connected: true } );

	it( 'names the unit or service chosen', () => {
		expect(
			describeChoice( { show: 'unit', unit: 'belle' }, catalog )
		).toBe( 'Booking for The Belle' );
		expect(
			describeChoice( { show: 'service', service: 'cut' }, catalog )
		).toBe( 'Appointments for Haircut' );
		expect(
			describeChoice( { show: 'unit', unit: 'other' }, catalog )
		).toBe( 'Booking for other' );
	} );

	it( 'asks for a choice when none is made yet', () => {
		expect( describeChoice( { show: 'unit', unit: '' }, catalog ) ).toBe(
			'All units (choose a unit in the sidebar)'
		);
		expect( describeChoice( { show: 'appointments' }, catalog ) ).toBe(
			'Appointments'
		);
		expect( describeChoice( { show: 'all' }, catalog ) ).toBe(
			'All units'
		);
	} );
} );

describe( 'Edit when the site is not connected', () => {
	/**
	 * Renders the block and returns its HTML.
	 *
	 * @param {Object} site Editor data.
	 */
	function render( site ) {
		const { default: Edit, element, react } = loadEdit( site );
		const { createElement, createRoot } = element;
		const { act } = react;
		const container = document.createElement( 'div' );
		const root = createRoot( container );
		global.IS_REACT_ACT_ENVIRONMENT = true;
		act( () => {
			root.render(
				createElement( Edit, {
					attributes: { show: 'all' },
					setAttributes: () => {},
				} )
			);
		} );
		const html = container.innerHTML;
		act( () => root.unmount() );
		return html;
	}

	it( 'offers administrators Connect, in a new tab', () => {
		const html = render( {
			connected: false,
			settingsUrl: 'https://example.com/wp-admin/admin.php?page=anybook',
		} );
		expect( html ).toContain( 'Connect to Anybook' );
		expect( html ).toContain(
			'data-href="https://example.com/wp-admin/admin.php?page=anybook"'
		);
		expect( html ).toContain( 'data-target="_blank"' );
	} );

	it( 'tells everyone else to ask the administrator', () => {
		const html = render( { connected: false, settingsUrl: '' } );
		expect( html ).toContain( 'administrator' );
		expect( html ).not.toContain( 'data-part="Button"' );
	} );
} );
