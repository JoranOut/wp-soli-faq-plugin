/**
 * "Members only" toggle in the FAQ editor sidebar, stored as the
 * soli_faq_public post meta (inverted: on means not public).
 */
import { ToggleControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginPostStatusInfo, store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

const POST_TYPE = 'soli_faq';
const META_KEY = 'soli_faq_public';

function MembersOnlyPanel() {
	const { postType, meta } = useSelect( ( select ) => {
		const editor = select( editorStore );
		return {
			postType: editor.getCurrentPostType(),
			meta: editor.getEditedPostAttribute( 'meta' ),
		};
	}, [] );
	const { editPost } = useDispatch( editorStore );

	if ( postType !== POST_TYPE || ! meta ) {
		return null;
	}

	const membersOnly = ! meta[ META_KEY ];

	return (
		<PluginPostStatusInfo className="soli-faq-members-only">
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Members only', 'soli-faq' ) }
				help={
					membersOnly
						? __( 'Only logged-in members can read this question.', 'soli-faq' )
						: __( 'Everyone can read this question, also without logging in.', 'soli-faq' )
				}
				checked={ membersOnly }
				onChange={ ( value ) =>
					editPost( { meta: { [ META_KEY ]: ! value } } )
				}
			/>
		</PluginPostStatusInfo>
	);
}

registerPlugin( 'soli-faq-members-only', { render: MembersOnlyPanel } );
