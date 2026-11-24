/**
 * The block in the editor: the choices in the sidebar and a preview card in the page. The live widget is not
 * loaded here (no remote script in the admin); it shows on the published page.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	PanelColorSettings,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	CheckboxControl,
	Notice,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/** What the server told the editor (Block::editor_data()). */
const site = window.anybookBlock || {};

/** The widget's own words, shown when a wording box is left empty. */
const DEFAULT_WORDS = {
	buttonText: __( 'Book and go to payment', 'anybook' ),
	detailsHeading: __( 'Your details', 'anybook' ),
	datesText: __(
		'Choose your arrival date, then your departure date.',
		'anybook'
	),
};

/** The overview's weeks: the fewest, the most and the default (Renderer::WEEKS_* in PHP). */
const WEEKS = { min: 2, max: 26, default: 12 };

/**
 * Weeks shown as a whole number from 2 to 26, or 12 when there is no number.
 *
 * @param {*} value The number chosen.
 * @return {number} Weeks.
 */
export function clampWeeks( value ) {
	const weeks = Math.round( Number( value ) );
	if ( value === undefined || value === null || ! Number.isFinite( weeks ) ) {
		return WEEKS.default;
	}
	return Math.min( WEEKS.max, Math.max( WEEKS.min, weeks ) );
}

/**
 * Loads the units and services once per editor (they are cached on the server for ten minutes as well).
 *
 * @param {boolean} enabled Whether to ask at all (only when connected).
 */
function useCatalog( enabled ) {
	const [ state, setState ] = useState( {
		loading: enabled,
		error: '',
		units: [],
		services: [],
	} );
	const [ refresh, setRefresh ] = useState( 0 );

	useEffect( () => {
		if ( ! enabled ) {
			return undefined;
		}
		let live = true;
		setState( ( old ) => ( { ...old, loading: true, error: '' } ) );
		apiFetch( {
			path: '/anybook/v1/catalog' + ( refresh ? '?refresh=1' : '' ),
		} )
			.then( ( data ) => {
				if ( live ) {
					setState( {
						loading: false,
						error: '',
						units: Array.isArray( data?.units ) ? data.units : [],
						services: Array.isArray( data?.services )
							? data.services
							: [],
					} );
				}
			} )
			.catch( ( error ) => {
				if ( live ) {
					setState( {
						loading: false,
						error:
							error?.message ||
							__(
								'Your units and services could not be loaded.',
								'anybook'
							),
						units: [],
						services: [],
					} );
				}
			} );
		return () => {
			live = false;
		};
	}, [ enabled, refresh ] );

	return { ...state, reload: () => setRefresh( ( n ) => n + 1 ) };
}

/**
 * A unit or service chooser, or a code box when the list could not be had.
 *
 * @param {Object}   props
 * @param {string}   props.label    Label.
 * @param {string}   props.value    Chosen code.
 * @param {Array}    props.items    `{code, name}` choices.
 * @param {boolean}  props.loading  Still loading.
 * @param {boolean}  props.failed   The list could not be loaded.
 * @param {string}   props.empty    Words when the business has none.
 * @param {Function} props.onChange Called with the new code.
 */
export function CodeChooser( {
	label,
	value,
	items,
	loading,
	failed,
	empty,
	onChange,
} ) {
	if ( loading ) {
		return <Spinner />;
	}
	if ( failed ) {
		return (
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ label }
				help={ __(
					'Type its code as Anybook shows it (letters, digits, - and _).',
					'anybook'
				) }
				value={ value }
				onChange={ ( code ) =>
					onChange( code.replace( /[^A-Za-z0-9_-]/g, '' ) )
				}
			/>
		);
	}
	if ( ! items.length ) {
		return <p>{ empty }</p>;
	}
	const known = items.some( ( item ) => item.code === value );
	const options = [
		{ label: __( 'Choose…', 'anybook' ), value: '' },
		...items.map( ( item ) => ( { label: item.name, value: item.code } ) ),
	];
	if ( value && ! known ) {
		options.push( {
			/* translators: %s: a unit or service code no longer offered. */
			label: sprintf( __( '%s (no longer offered)', 'anybook' ), value ),
			value,
		} );
	}
	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ label }
			value={ value }
			options={ options }
			onChange={ onChange }
		/>
	);
}

/**
 * Which units the overview shows: a tick box per unit (none ticked is all units), or a box for their codes when
 * the list could not be had.
 *
 * @param {Object}   props
 * @param {string}   props.value    Chosen codes, comma-separated ('' for all).
 * @param {Array}    props.items    `{code, name}` units.
 * @param {boolean}  props.loading  Still loading.
 * @param {boolean}  props.failed   The list could not be loaded.
 * @param {Function} props.onChange Called with the new codes, comma-separated.
 */
export function UnitsChooser( { value, items, loading, failed, onChange } ) {
	const label = __( 'Units', 'anybook' );
	if ( loading ) {
		return <Spinner />;
	}
	if ( failed ) {
		return (
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ label }
				help={ __(
					'Type their codes as Anybook shows them, separated by commas. Leave it empty for all your units.',
					'anybook'
				) }
				value={ value }
				onChange={ ( codes ) =>
					onChange(
						codes.toLowerCase().replace( /[^a-z0-9,-]/g, '' )
					)
				}
			/>
		);
	}
	if ( ! items.length ) {
		return (
			<p>
				{ __(
					'Your business has no units customers can book online.',
					'anybook'
				) }
			</p>
		);
	}
	const chosen = String( value || '' )
		.split( ',' )
		.filter( Boolean );
	const toggle = ( code, on ) => {
		const next = on
			? [ ...chosen, code ]
			: chosen.filter( ( item ) => item !== code );
		// In the catalogue's order; a code no longer offered stays at the end.
		const known = items
			.map( ( item ) => item.code )
			.filter( ( item ) => next.includes( item ) );
		const gone = next.filter(
			( item ) => ! items.some( ( unit ) => unit.code === item )
		);
		onChange( [ ...new Set( [ ...known, ...gone ] ) ].join( ',' ) );
	};
	return (
		<fieldset className="anybook-booking-units">
			<legend>{ label }</legend>
			<p className="anybook-booking-help">
				{ __(
					'Tick the units to show. None ticked shows all your units.',
					'anybook'
				) }
			</p>
			{ items.map( ( item ) => (
				<CheckboxControl
					key={ item.code }
					__nextHasNoMarginBottom
					label={ item.name }
					checked={ chosen.includes( item.code ) }
					onChange={ ( on ) => toggle( item.code, on ) }
				/>
			) ) }
		</fieldset>
	);
}

/**
 * The overview's choices in the sidebar: weeks, prices, units and the appointment rows.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Sets block attributes.
 * @param {Object}   props.catalog       Units and services.
 */
export function OverviewChoices( { attributes, setAttributes, catalog } ) {
	const failed = !! catalog.error;
	const rows = 'people' === attributes.rows ? 'people' : 'services';
	return (
		<>
			<RangeControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Weeks shown', 'anybook' ) }
				min={ WEEKS.min }
				max={ WEEKS.max }
				value={ clampWeeks( attributes.weeks ) }
				onChange={ ( weeks ) =>
					setAttributes( { weeks: clampWeeks( weeks ) } )
				}
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Show prices', 'anybook' ) }
				checked={ !! attributes.prices }
				onChange={ ( prices ) => setAttributes( { prices } ) }
			/>
			<UnitsChooser
				value={ attributes.units || '' }
				items={ catalog.units }
				loading={ catalog.loading }
				failed={ failed }
				onChange={ ( units ) => setAttributes( { units } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Appointment rows', 'anybook' ) }
				value={ rows }
				options={ [
					{
						label: __( 'One row per service', 'anybook' ),
						value: 'services',
					},
					{
						label: __( 'One row per team member', 'anybook' ),
						value: 'people',
					},
				] }
				onChange={ ( value ) => setAttributes( { rows: value } ) }
			/>
			{ 'people' === rows && (
				<CodeChooser
					label={ __( 'Service', 'anybook' ) }
					value={ attributes.service || '' }
					// The overview takes the service's id.
					items={ catalog.services.map( ( item ) => ( {
						code: item.id,
						name: item.name,
					} ) ) }
					loading={ catalog.loading }
					failed={ failed }
					empty={ __(
						'Your business has no services customers can book online.',
						'anybook'
					) }
					onChange={ ( service ) => setAttributes( { service } ) }
				/>
			) }
		</>
	);
}

/**
 * Shown in place of the widget while the site is not connected.
 *
 * @param {Object} props
 * @param {Object} props.blockProps The block wrapper's props.
 */
function NotConnected( { blockProps } ) {
	return (
		<div { ...blockProps }>
			<Placeholder
				icon="calendar-alt"
				label={ __( 'Anybook booking', 'anybook' ) }
				instructions={ __(
					'Connect this site to Anybook and your booking widget will show here.',
					'anybook'
				) }
			>
				{ site.settingsUrl ? (
					<Button
						variant="primary"
						href={ site.settingsUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Connect to Anybook', 'anybook' ) }
					</Button>
				) : (
					<p>
						{ __(
							'Ask the site’s administrator to connect it (Anybook in the WordPress admin menu).',
							'anybook'
						) }
					</p>
				) }
			</Placeholder>
		</div>
	);
}

/**
 * What the preview card says it shows.
 *
 * @param {Object} attributes Block attributes.
 * @param {Object} catalog    Units and services.
 */
export function describe( attributes, catalog ) {
	const nameOf = ( list, code ) =>
		list.find( ( item ) => item.code === code )?.name || code;
	switch ( attributes.show ) {
		case 'overview':
			return describeOverview( attributes, catalog );
		case 'unit':
			return attributes.unit
				? sprintf(
						/* translators: %s: unit name. */
						__( 'Booking for %s', 'anybook' ),
						nameOf( catalog.units, attributes.unit )
				  )
				: __( 'All units (choose a unit in the sidebar)', 'anybook' );
		case 'appointments':
			return __( 'Appointments', 'anybook' );
		case 'service':
			return attributes.service
				? sprintf(
						/* translators: %s: service name. */
						__( 'Appointments for %s', 'anybook' ),
						nameOf( catalog.services, attributes.service )
				  )
				: __(
						'Appointments (choose a service in the sidebar)',
						'anybook'
				  );
		default:
			return __( 'All units', 'anybook' );
	}
}

/**
 * What the preview card says for the overview.
 *
 * @param {Object} attributes Block attributes.
 * @param {Object} catalog    Units and services.
 */
function describeOverview( attributes, catalog ) {
	let words = sprintf(
		/* translators: %d: number of weeks (2 to 26). */
		__( 'Availability overview, %d weeks', 'anybook' ),
		clampWeeks( attributes.weeks )
	);
	if ( attributes.prices ) {
		words += __( ', with prices', 'anybook' );
	}
	const units = String( attributes.units || '' )
		.split( ',' )
		.filter( Boolean )
		.map(
			( code ) =>
				catalog.units.find( ( item ) => item.code === code )?.name ||
				code
		);
	if ( units.length ) {
		words += ': ' + units.join( ', ' );
	}
	if ( 'people' === attributes.rows ) {
		const service = catalog.services.find(
			( item ) => item.id === attributes.service
		);
		words +=
			': ' +
			( attributes.service
				? sprintf(
						/* translators: %s: service name. */
						__( 'team members for %s', 'anybook' ),
						service?.name || attributes.service
				  )
				: __(
						'team members (choose a service in the sidebar)',
						'anybook'
				  ) );
	}
	return words;
}

/**
 * The widget's colors, wording and guest-link choices (not shown for the overview, which has none of them).
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Sets block attributes.
 */
function WidgetPanels( { attributes, setAttributes } ) {
	return (
		<>
			<PanelColorSettings
				title={ __( 'Colors', 'anybook' ) }
				initialOpen={ false }
				colorSettings={ [
					{
						label: __( 'Main color', 'anybook' ),
						value: attributes.accent,
						onChange: ( value ) =>
							setAttributes( { accent: value || '' } ),
					},
					{
						label: __( 'Text color', 'anybook' ),
						value: attributes.text,
						onChange: ( value ) =>
							setAttributes( { text: value || '' } ),
					},
				] }
			>
				<p className="anybook-booking-help">
					{ __(
						'Left empty, the colors on the Anybook settings page are used.',
						'anybook'
					) }
				</p>
			</PanelColorSettings>
			<PanelBody
				title={ __( 'Wording', 'anybook' ) }
				initialOpen={ false }
			>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Booking button', 'anybook' ) }
					placeholder={ DEFAULT_WORDS.buttonText }
					value={ attributes.buttonText }
					onChange={ ( buttonText ) =>
						setAttributes( { buttonText } )
					}
				/>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Details heading', 'anybook' ) }
					placeholder={ DEFAULT_WORDS.detailsHeading }
					value={ attributes.detailsHeading }
					onChange={ ( detailsHeading ) =>
						setAttributes( { detailsHeading } )
					}
				/>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Dates line', 'anybook' ) }
					placeholder={ DEFAULT_WORDS.datesText }
					value={ attributes.datesText }
					onChange={ ( datesText ) => setAttributes( { datesText } ) }
				/>
			</PanelBody>
			<PanelBody
				title={ __( 'Customer links', 'anybook' ) }
				initialOpen={ false }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Use this widget for customers’ email links',
						'anybook'
					) }
					help={ __(
						'Turn on for the widget on your customer page, so links in customers’ emails (manage, pay, extras) open here.',
						'anybook'
					) }
					checked={ !! attributes.guestLinks }
					onChange={ ( guestLinks ) =>
						setAttributes( { guestLinks } )
					}
				/>
			</PanelBody>
		</>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'anybook-booking-preview',
	} );
	const catalog = useCatalog( !! site.connected );

	if ( ! site.connected ) {
		return <NotConnected blockProps={ blockProps } />;
	}

	const accent = attributes.accent || site.accent || '';
	const textColor = attributes.text || site.text || '';
	const failed = !! catalog.error;
	const overview = 'overview' === attributes.show;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'What to show', 'anybook' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Show', 'anybook' ) }
						value={ attributes.show }
						options={ [
							{
								label: __( 'All units', 'anybook' ),
								value: 'all',
							},
							{
								label: __( 'One unit', 'anybook' ),
								value: 'unit',
							},
							{
								label: __( 'Appointments', 'anybook' ),
								value: 'appointments',
							},
							{
								label: __( 'One service', 'anybook' ),
								value: 'service',
							},
							{
								label: __( 'Availability overview', 'anybook' ),
								value: 'overview',
							},
						] }
						onChange={ ( show ) => setAttributes( { show } ) }
					/>
					{ failed &&
						[ 'unit', 'service', 'overview' ].includes(
							attributes.show
						) && (
							<Notice status="warning" isDismissible={ false }>
								{ catalog.error }{ ' ' }
								<Button
									variant="link"
									onClick={ catalog.reload }
								>
									{ __( 'Try again', 'anybook' ) }
								</Button>
							</Notice>
						) }
					{ 'unit' === attributes.show && (
						<CodeChooser
							label={ __( 'Unit', 'anybook' ) }
							value={ attributes.unit }
							items={ catalog.units }
							loading={ catalog.loading }
							failed={ failed }
							empty={ __(
								'Your business has no units customers can book online.',
								'anybook'
							) }
							onChange={ ( unit ) => setAttributes( { unit } ) }
						/>
					) }
					{ 'service' === attributes.show && (
						<CodeChooser
							label={ __( 'Service', 'anybook' ) }
							value={ attributes.service }
							items={ catalog.services }
							loading={ catalog.loading }
							failed={ failed }
							empty={ __(
								'Your business has no services customers can book online.',
								'anybook'
							) }
							onChange={ ( service ) =>
								setAttributes( { service } )
							}
						/>
					) }
					{ overview && (
						<OverviewChoices
							attributes={ attributes }
							setAttributes={ setAttributes }
							catalog={ catalog }
						/>
					) }
				</PanelBody>
				{ ! overview && (
					<WidgetPanels
						attributes={ attributes }
						setAttributes={ setAttributes }
					/>
				) }
			</InspectorControls>
			<div { ...blockProps }>
				<div
					className="anybook-booking-preview__card"
					style={ {
						borderColor: accent || undefined,
						color: textColor || undefined,
					} }
				>
					<p className="anybook-booking-preview__title">
						{ site.name || __( 'Anybook booking', 'anybook' ) }
					</p>
					<p>{ describe( attributes, catalog ) }</p>
					{ ! overview && (
						<span
							className="anybook-booking-preview__button"
							style={ { background: accent || undefined } }
						>
							{ attributes.buttonText ||
								DEFAULT_WORDS.buttonText }
						</span>
					) }
					{ ! overview && attributes.guestLinks && (
						<p className="anybook-booking-preview__note">
							{ __( 'Opens customers’ email links.', 'anybook' ) }
						</p>
					) }
					<p className="anybook-booking-preview__note">
						{ __(
							'The live booking widget shows on the published page.',
							'anybook'
						) }{ ' ' }
						{ site.settingsUrl && (
							<a href={ site.settingsUrl }>
								{ __( 'Anybook settings', 'anybook' ) }
							</a>
						) }
					</p>
				</div>
			</div>
		</>
	);
}
