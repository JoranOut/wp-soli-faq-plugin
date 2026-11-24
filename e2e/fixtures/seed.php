<?php
/**
 * Seeds fixture content for the e2e tests. Run via `wp eval-file`.
 * Idempotent: existing fixture posts are reused. Prints JSON with the
 * created ids and urls.
 */

$faq_title = 'Geheime FAQ voor leden';

$existing = get_posts(
	array(
		'post_type'   => 'soli_faq',
		'post_status' => 'publish',
		'title'       => $faq_title,
		'numberposts' => 1,
	)
);

if ( $existing ) {
	$faq_id = $existing[0]->ID;
} else {
	$faq_id = wp_insert_post(
		array(
			'post_type'    => 'soli_faq',
			'post_status'  => 'publish',
			'post_title'   => $faq_title,
			'post_content' => 'Alleen voor ingelogde leden bedoeld.',
		)
	);
}

$page_title = 'FAQ overzicht';

$existing_page = get_posts(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'title'       => $page_title,
		'numberposts' => 1,
	)
);

if ( $existing_page ) {
	$page_id = $existing_page[0]->ID;
} else {
	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $page_title,
			'post_content' => file_get_contents( __DIR__ . '/query-loop-page.html' ),
		)
	);
}

// Topics and FAQs for the search block. Long titles and several paragraphs,
// as the real content has.
$soli_faq_topic_ids = array();
foreach ( array( 'vrijwilligers' => 'Vrijwilligers', 'activiteiten' => 'Activiteiten & feesten' ) as $slug => $name ) {
	$term = get_term_by( 'slug', $slug, 'soli_faq_topic' );
	$soli_faq_topic_ids[ $slug ] = $term
		? (int) $term->term_id
		: (int) wp_insert_term( $name, 'soli_faq_topic', array( 'slug' => $slug ) )['term_id'];
}

$search_faqs = array(
	array(
		'title'   => 'Hoe kan ik mij inzetten voor de vereniging?',
		'topic'   => 'vrijwilligers',
		'content' => "<!-- wp:paragraph -->\n<p>Soli draait volledig op vrijwilligers. Je hoeft geen grote verplichting aan te gaan.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>De activiteitencommissie organiseert de feestavonden en de barbecue.</p>\n<!-- /wp:paragraph -->",
	),
	array(
		'title'   => 'Hoe organiseer ik een feestje of borrel voor mijn orkest?',
		'topic'   => 'activiteiten',
		'content' => "<!-- wp:paragraph -->\n<p>Meld het minimaal drie weken van tevoren bij de activiteitencommissie.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>De bar mag alleen worden bediend door iemand met een certificaat Sociale Hygiëne.</p>\n<!-- /wp:paragraph -->",
	),
);

foreach ( $search_faqs as $faq ) {
	$found = get_posts(
		array(
			'post_type'   => 'soli_faq',
			'post_status' => 'publish',
			'title'       => $faq['title'],
			'numberposts' => 1,
		)
	);
	$id = $found ? $found[0]->ID : wp_insert_post(
		array(
			'post_type'    => 'soli_faq',
			'post_status'  => 'publish',
			'post_title'   => $faq['title'],
			'post_content' => $faq['content'],
		)
	);
	wp_set_object_terms( $id, array( $soli_faq_topic_ids[ $faq['topic'] ] ), 'soli_faq_topic' );
}

// A public FAQ, without topic, that logged-out visitors may read.
$public_title = 'Waar vind ik het Muziekcentrum?';
$found_public = get_posts(
	array(
		'post_type'   => 'soli_faq',
		'post_status' => 'publish',
		'title'       => $public_title,
		'numberposts' => 1,
	)
);
$public_faq_id = $found_public ? $found_public[0]->ID : wp_insert_post(
	array(
		'post_type'    => 'soli_faq',
		'post_status'  => 'publish',
		'post_title'   => $public_title,
		'post_content' => "<!-- wp:paragraph -->\n<p>Het Muziekcentrum ligt in Driehuis, vlak bij het station.</p>\n<!-- /wp:paragraph -->",
	)
);
update_post_meta( $public_faq_id, 'soli_faq_public', true );

$search_page_title = 'FAQ zoeken';
$existing_search_page = get_posts(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'title'       => $search_page_title,
		'numberposts' => 1,
	)
);
$search_page_id = $existing_search_page ? $existing_search_page[0]->ID : wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $search_page_title,
		'post_content' => '<!-- wp:soli-faq/search /-->',
	)
);

echo wp_json_encode(
	array(
		'faqId'  => $faq_id,
		'faqUrl' => get_permalink( $faq_id ),
		'pageUrl' => get_permalink( $page_id ),
		'searchPageUrl' => get_permalink( $search_page_id ),
		'publicFaqId'   => $public_faq_id,
		'publicFaqUrl'  => get_permalink( $public_faq_id ),
	)
);
