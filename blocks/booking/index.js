/**
 * The Anybook booking block (ADR-6). Rendered on the server by the same renderer as the [anybook] shortcode, so
 * the block saves no HTML: only its attributes.
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
