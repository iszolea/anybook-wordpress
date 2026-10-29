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
	Notice,
	PanelBody,
	Placeholder,
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

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'anybook-booking-preview',
	} );
	const catalog = useCatalog( !! site.connected );

	if ( ! site.connected ) {
		return <NotConnected blockProps={ blockProps } />;
	}

	const accent = attributes.accent || site.accent || '';
	const textColour = attributes.text || site.text || '';
	const failed = !! catalog.error;

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
						] }
						onChange={ ( show ) => setAttributes( { show } ) }
					/>
					{ failed &&
						[ 'unit', 'service' ].includes( attributes.show ) && (
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
								'Your business has no units guests can book online.',
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
								'Your business has no services guests can book online.',
								'anybook'
							) }
							onChange={ ( service ) =>
								setAttributes( { service } )
							}
						/>
					) }
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Colours', 'anybook' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							label: __( 'Main colour', 'anybook' ),
							value: attributes.accent,
							onChange: ( value ) =>
								setAttributes( { accent: value || '' } ),
						},
						{
							label: __( 'Text colour', 'anybook' ),
							value: attributes.text,
							onChange: ( value ) =>
								setAttributes( { text: value || '' } ),
						},
					] }
				>
					<p className="anybook-booking-help">
						{ __(
							'Left empty, the colours on the Anybook settings page are used.',
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
						onChange={ ( datesText ) =>
							setAttributes( { datesText } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Guest links', 'anybook' ) }
					initialOpen={ false }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Use this widget for guests’ email links',
							'anybook'
						) }
						help={ __(
							'Turn on for the widget on your guest page, so links in guests’ emails (manage, pay, extras) open here.',
							'anybook'
						) }
						checked={ !! attributes.guestLinks }
						onChange={ ( guestLinks ) =>
							setAttributes( { guestLinks } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div
					className="anybook-booking-preview__card"
					style={ {
						borderColor: accent || undefined,
						color: textColour || undefined,
					} }
				>
					<p className="anybook-booking-preview__title">
						{ site.name || __( 'Anybook booking', 'anybook' ) }
					</p>
					<p>{ describe( attributes, catalog ) }</p>
					<span
						className="anybook-booking-preview__button"
						style={ { background: accent || undefined } }
					>
						{ attributes.buttonText || DEFAULT_WORDS.buttonText }
					</span>
					{ attributes.guestLinks && (
						<p className="anybook-booking-preview__note">
							{ __( 'Opens guests’ email links.', 'anybook' ) }
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
