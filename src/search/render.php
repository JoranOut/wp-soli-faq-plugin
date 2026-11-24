<?php
/**
 * Server render of the FAQ search block. Works without JavaScript through
 * GET parameters; view.js takes over filtering in place once it loads.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

use Soli\Faq\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$soli_faq_wrapper = get_block_wrapper_attributes();

$soli_faq_items     = Search::items();
$soli_faq_logged_in = is_user_logged_in();
$soli_faq_login_url = wp_login_url( get_permalink() ?: home_url( '/' ) );

if ( ! $soli_faq_logged_in && ! $soli_faq_items ) {
	printf(
		'<div %1$s><p class="soli-faq-search__login">%2$s</p></div>',
		$soli_faq_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_kses(
			sprintf(
				/* translators: %s: login URL */
				__( 'The frequently asked questions are for members only. <a href="%s">Log in</a> to read them.', 'soli-faq' ),
				esc_url( $soli_faq_login_url )
			),
			array( 'a' => array( 'href' => array() ) )
		)
	);
	return;
}

$soli_faq_login_note = ! $soli_faq_logged_in && Search::has_members_only()
	? wp_kses(
		sprintf(
			/* translators: %s: login URL */
			__( 'Some questions are for members only. <a href="%s">Log in</a> to see them all.', 'soli-faq' ),
			esc_url( $soli_faq_login_url )
		),
		array( 'a' => array( 'href' => array() ) )
	)
	: '';

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET search
$soli_faq_query = isset( $_GET[ Search::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ Search::QUERY_VAR ] ) ) : '';
$soli_faq_topic = isset( $_GET[ Search::TOPIC_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ Search::TOPIC_VAR ] ) ) : '';
// phpcs:enable

$soli_faq_show_topics = ! empty( $attributes['showTopics'] );
$soli_faq_topics      = $soli_faq_show_topics ? Search::topics( $soli_faq_items ) : array();
$soli_faq_topic_names = wp_list_pluck( $soli_faq_topics, 'name', 'slug' );
if ( ! isset( $soli_faq_topic_names[ $soli_faq_topic ] ) ) {
	$soli_faq_topic = '';
}

$soli_faq_matches = Search::filter( $soli_faq_items, $soli_faq_query );
$soli_faq_hits    = $soli_faq_topic
	? array_values( array_filter( $soli_faq_matches, static fn( $item ) => in_array( $soli_faq_topic, $item['topics'], true ) ) )
	: $soli_faq_matches;

$soli_faq_base = remove_query_arg( array( Search::QUERY_VAR, Search::TOPIC_VAR ) );
$soli_faq_id   = wp_unique_id( 'soli-faq-search-' );

$soli_faq_i18n = array(
	'countOne'       => _n( '%1$s question', '%1$s questions', 1, 'soli-faq' ),
	'countMany'      => _n( '%1$s question', '%1$s questions', 2, 'soli-faq' ),
	/* translators: 1: number of questions, 2: search query */
	'countQueryOne'  => _n( '%1$s question matching “%2$s”', '%1$s questions matching “%2$s”', 1, 'soli-faq' ),
	/* translators: 1: number of questions, 2: search query */
	'countQueryMany' => _n( '%1$s question matching “%2$s”', '%1$s questions matching “%2$s”', 2, 'soli-faq' ),
	/* translators: %s: topic name */
	'inTopic'        => __( 'in %s', 'soli-faq' ),
	'showAll'        => __( 'show all topics', 'soli-faq' ),
	'readAnswer'     => __( 'Read the answer', 'soli-faq' ),
	/* translators: %s: date */
	'updated'        => __( 'Updated %s', 'soli-faq' ),
	/* translators: %s: search query */
	'emptyTitle'     => __( 'No questions matching “%s”', 'soli-faq' ),
	'emptyText'      => __( 'Try a different or shorter word, or pick another topic.', 'soli-faq' ),
	'none'           => __( 'There are no frequently asked questions yet.', 'soli-faq' ),
);

$soli_faq_count_label = static function ( int $count, string $query, string $topic_name ) use ( $soli_faq_i18n ) {
	$count_html = '<strong>' . number_format_i18n( $count ) . '</strong>';
	$label      = '' !== trim( $query )
		? sprintf( esc_html( 1 === $count ? $soli_faq_i18n['countQueryOne'] : $soli_faq_i18n['countQueryMany'] ), $count_html, esc_html( trim( $query ) ) )
		: sprintf( esc_html( 1 === $count ? $soli_faq_i18n['countOne'] : $soli_faq_i18n['countMany'] ), $count_html );
	if ( $topic_name ) {
		$label .= ' ' . esc_html( sprintf( $soli_faq_i18n['inTopic'], $topic_name ) );
	}
	return $label;
};
?>
<div <?php echo $soli_faq_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-soli-faq-search>
	<?php if ( ! $soli_faq_items ) : ?>
		<p class="soli-faq-search__empty-text"><?php echo esc_html( $soli_faq_i18n['none'] ); ?></p>
	<?php else : ?>
	<form class="soli-faq-search__form" role="search" method="get" action="<?php echo esc_url( $soli_faq_base ); ?>">
		<label class="screen-reader-text" for="<?php echo esc_attr( $soli_faq_id ); ?>"><?php esc_html_e( 'Search the FAQs', 'soli-faq' ); ?></label>
		<input
			id="<?php echo esc_attr( $soli_faq_id ); ?>"
			class="soli-faq-search__input"
			type="search"
			name="<?php echo esc_attr( Search::QUERY_VAR ); ?>"
			value="<?php echo esc_attr( $soli_faq_query ); ?>"
			placeholder="<?php esc_attr_e( 'Search for a word, for example uniform or membership fee', 'soli-faq' ); ?>"
			autocomplete="off"
		>
		<?php if ( $soli_faq_topic ) : ?>
			<input type="hidden" name="<?php echo esc_attr( Search::TOPIC_VAR ); ?>" value="<?php echo esc_attr( $soli_faq_topic ); ?>">
		<?php endif; ?>
		<button class="soli-faq-search__submit wp-element-button" type="submit"><?php esc_html_e( 'Search', 'soli-faq' ); ?></button>
	</form>
	<?php if ( $soli_faq_login_note ) : ?>
		<p class="soli-faq-search__login-note"><?php echo $soli_faq_login_note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<?php endif; ?>

	<div class="soli-faq-search__layout<?php echo $soli_faq_topics ? '' : ' soli-faq-search__layout--no-topics'; ?>">
		<?php if ( $soli_faq_topics ) : ?>
		<nav class="soli-faq-search__topics" aria-label="<?php esc_attr_e( 'Topics', 'soli-faq' ); ?>">
			<h2 class="soli-faq-search__topics-title"><?php esc_html_e( 'Topics', 'soli-faq' ); ?></h2>
			<ul>
				<?php
				$soli_faq_topic_links = array_merge(
					array(
						array(
							'slug' => '',
							'name' => __( 'All topics', 'soli-faq' ),
						),
					),
					$soli_faq_topics
				);
				foreach ( $soli_faq_topic_links as $soli_faq_link ) :
					$soli_faq_n = $soli_faq_link['slug']
						? count( array_filter( $soli_faq_matches, static fn( $item ) => in_array( $soli_faq_link['slug'], $item['topics'], true ) ) )
						: count( $soli_faq_matches );
					$soli_faq_href = add_query_arg(
						array_filter(
							array(
								Search::QUERY_VAR => $soli_faq_query,
								Search::TOPIC_VAR => $soli_faq_link['slug'],
							)
						),
						$soli_faq_base
					);
					$soli_faq_current = $soli_faq_link['slug'] === $soli_faq_topic;
					?>
					<li>
						<a
							class="soli-faq-search__topic"
							href="<?php echo esc_url( $soli_faq_href ); ?>"
							data-topic="<?php echo esc_attr( $soli_faq_link['slug'] ); ?>"
							<?php echo $soli_faq_current ? 'aria-current="true"' : ''; ?>
							<?php echo ( ! $soli_faq_n && ! $soli_faq_current ) ? 'aria-disabled="true"' : ''; ?>
						><?php echo esc_html( $soli_faq_link['name'] ); ?><span class="soli-faq-search__topic-count"><?php echo esc_html( number_format_i18n( $soli_faq_n ) ); ?></span></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php endif; ?>

		<section class="soli-faq-search__main">
			<p class="soli-faq-search__count" aria-live="polite">
				<?php echo $soli_faq_count_label( count( $soli_faq_hits ), $soli_faq_query, $soli_faq_topic_names[ $soli_faq_topic ] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>
			<ul class="soli-faq-search__results">
				<?php if ( ! $soli_faq_hits ) : ?>
					<li class="soli-faq-search__empty">
						<h2 class="soli-faq-search__empty-title"><?php echo esc_html( sprintf( $soli_faq_i18n['emptyTitle'], $soli_faq_query ) ); ?></h2>
						<p class="soli-faq-search__empty-text"><?php echo esc_html( $soli_faq_i18n['emptyText'] ); ?></p>
					</li>
				<?php endif; ?>
				<?php foreach ( $soli_faq_hits as $soli_faq_item ) : ?>
					<li class="soli-faq-search__result">
						<h2 class="soli-faq-search__title"><a href="<?php echo esc_url( $soli_faq_item['url'] ); ?>"><?php echo esc_html( $soli_faq_item['title'] ); ?></a></h2>
						<p class="soli-faq-search__snippet"><?php echo esc_html( $soli_faq_item['excerpt'] ); ?></p>
						<div class="soli-faq-search__meta">
							<?php foreach ( $soli_faq_item['topics'] as $soli_faq_slug ) : ?>
								<?php if ( isset( $soli_faq_topic_names[ $soli_faq_slug ] ) ) : ?>
									<span><?php echo esc_html( $soli_faq_topic_names[ $soli_faq_slug ] ); ?></span>
								<?php endif; ?>
							<?php endforeach; ?>
							<span><?php echo esc_html( sprintf( $soli_faq_i18n['updated'], $soli_faq_item['updated'] ) ); ?></span>
							<a class="soli-faq-search__more" href="<?php echo esc_url( $soli_faq_item['url'] ); ?>" tabindex="-1" aria-hidden="true"><?php echo esc_html( $soli_faq_i18n['readAnswer'] ); ?> <span>→</span></a>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>

	<script type="application/json" class="soli-faq-search__data"><?php
		echo wp_json_encode(
			array(
				'items'     => $soli_faq_items,
				'topics'    => $soli_faq_topics,
				'queryVar'  => Search::QUERY_VAR,
				'topicVar'  => Search::TOPIC_VAR,
				'i18n'      => $soli_faq_i18n,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
		);
	?></script>
	<?php endif; ?>
</div>
