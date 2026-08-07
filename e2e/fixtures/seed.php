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

echo wp_json_encode(
	array(
		'faqId'  => $faq_id,
		'faqUrl' => get_permalink( $faq_id ),
		'pageUrl' => get_permalink( $page_id ),
	)
);
