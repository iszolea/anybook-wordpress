/**
 * The Anybook booking block (ADR-6). Rendered on the server by the same renderer as the [anybook] shortcode, so
 * the block saves no HTML: only its attributes.
 */
import { createBlock, registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import { attributesFromShortcode, isAnybookShortcode } from './shortcode';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
	// An existing [anybook] Shortcode block can become the block, keeping its choices (review Q11d).
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/shortcode' ],
				isMatch: ( { text } ) => isAnybookShortcode( text ),
				transform: ( { text } ) =>
					createBlock(
						metadata.name,
						attributesFromShortcode( text )
					),
			},
		],
	},
} );
