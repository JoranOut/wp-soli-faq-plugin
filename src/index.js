/**
 * Registers a "FAQs" variation of the core Query Loop block,
 * preconfigured to list the newest FAQs.
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

const VARIATION_NAME = 'soli-faq/faq-loop';
const POST_TYPE = 'soli_faq';

registerBlockVariation( 'core/query', {
	name: VARIATION_NAME,
	title: __( 'FAQs', 'soli-faq' ),
	description: __(
		'Displays the latest FAQs (members-only frequently asked questions).',
		'soli-faq'
	),
	icon: 'editor-help',
	attributes: {
		namespace: VARIATION_NAME,
		query: {
			postType: POST_TYPE,
			perPage: 5,
			offset: 0,
			order: 'desc',
			orderBy: 'date',
			inherit: false,
		},
	},
	innerBlocks: [
		[
			'core/post-template',
			{},
			[
				[ 'core/post-title', { isLink: true } ],
				[ 'core/post-date' ],
				[ 'core/post-excerpt' ],
			],
		],
		[ 'core/query-no-results' ],
	],
	scope: [ 'inserter', 'block' ],
	isActive: ( blockAttributes ) =>
		blockAttributes.namespace === VARIATION_NAME ||
		blockAttributes.query?.postType === POST_TYPE,
} );
