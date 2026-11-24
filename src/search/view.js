/**
 * Live filtering for the FAQ search block. The server renders the same list
 * for the initial URL; this only re-renders in place and keeps the URL in sync,
 * so going back from an answer restores the search.
 */

const normalize = ( s ) =>
	s
		.toLowerCase()
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' );

const escapeHtml = ( s ) =>
	String( s ).replace(
		/[&<>"']/g,
		( c ) =>
			( {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;',
			} )[ c ]
	);

const termsOf = ( query ) =>
	normalize( query )
		.split( /\s+/ )
		.filter( ( t ) => t.length > 1 );

const format = ( template, ...args ) =>
	template.replace( /%(\d)\$s|%s/g, ( match, n ) =>
		n ? args[ n - 1 ] : args[ 0 ]
	);

// Same scoring as Search::filter() in PHP.
function filter( items, query ) {
	const terms = termsOf( query );
	if ( ! terms.length ) {
		return items;
	}
	return items
		.map( ( item, index ) => {
			const title = normalize( item.title );
			const body = normalize( item.text );
			let score = 0;
			for ( const term of terms ) {
				const inTitle = title.includes( term );
				const inBody = body.split( term ).length - 1;
				if ( ! inTitle && ! inBody ) {
					return null;
				}
				score += ( inTitle ? 10 : 0 ) + Math.min( inBody, 5 );
			}
			return { item, index, score };
		} )
		.filter( Boolean )
		.sort( ( a, b ) => b.score - a.score || a.index - b.index )
		.map( ( r ) => r.item );
}

/**
 * Wraps matches in <mark>. Matching runs on the accent-free text, so the
 * marks are placed per character to keep the original spelling.
 */
function highlight( text, query ) {
	const terms = termsOf( query );
	if ( ! terms.length ) {
		return escapeHtml( text );
	}
	const chars = Array.from( text );
	const normalized = chars.map( normalize );
	// One normalized char can be longer than one char ("ß"); bail out to plain text then.
	if ( normalized.some( ( c ) => c.length !== 1 ) ) {
		return escapeHtml( text );
	}
	const flat = normalized.join( '' );
	const marked = new Array( chars.length ).fill( false );
	for ( const term of terms ) {
		let i = flat.indexOf( term );
		while ( i !== -1 ) {
			marked.fill( true, i, i + term.length );
			i = flat.indexOf( term, i + term.length );
		}
	}
	let out = '';
	chars.forEach( ( c, i ) => {
		if ( marked[ i ] && ! marked[ i - 1 ] ) {
			out += '<mark>';
		}
		out += escapeHtml( c );
		if ( marked[ i ] && ! marked[ i + 1 ] ) {
			out += '</mark>';
		}
	} );
	return out;
}

// Excerpt when there is no query; otherwise a window of text around the first match.
function snippet( item, query, length = 220 ) {
	const terms = termsOf( query );
	if ( ! terms.length ) {
		return escapeHtml( item.excerpt );
	}
	const text = item.text;
	const flat = normalize( text );
	const hits = terms
		.map( ( t ) => flat.indexOf( t ) )
		.filter( ( i ) => i !== -1 );
	const at = hits.length ? Math.min( ...hits ) : 0;
	const start = at < 60 ? 0 : text.lastIndexOf( ' ', at - 60 ) + 1;
	let piece = text.slice( start, start + length );
	const cut = start + length < text.length;
	if ( cut ) {
		piece = piece.replace( /\s\S*$/, '' );
	}
	return (
		( start > 0 ? '…' : '' ) +
		highlight( piece, query ) +
		( cut ? '…' : '' )
	);
}

function init( root ) {
	const dataEl = root.querySelector( '.soli-faq-search__data' );
	if ( ! dataEl ) {
		return;
	}
	const data = JSON.parse( dataEl.textContent );
	const { i18n } = data;
	const topicNames = Object.fromEntries(
		data.topics.map( ( t ) => [ t.slug, t.name ] )
	);
	const form = root.querySelector( '.soli-faq-search__form' );
	const input = root.querySelector( '.soli-faq-search__input' );
	const count = root.querySelector( '.soli-faq-search__count' );
	const results = root.querySelector( '.soli-faq-search__results' );
	const topicLinks = [ ...root.querySelectorAll( '.soli-faq-search__topic' ) ];

	const url = new URL( window.location.href );
	let query = input.value;
	let topic = url.searchParams.get( data.topicVar ) || '';
	if ( ! topicNames[ topic ] ) {
		topic = '';
	}

	function syncUrl() {
		const next = new URL( window.location.href );
		const set = ( key, value ) =>
			value
				? next.searchParams.set( key, value )
				: next.searchParams.delete( key );
		set( data.queryVar, query.trim() );
		set( data.topicVar, topic );
		window.history.replaceState( null, '', next );
	}

	function countLabel( n, topicName ) {
		const strong = `<strong>${ n.toLocaleString() }</strong>`;
		const q = query.trim();
		let label = q
			? format(
					escapeHtml(
						n === 1 ? i18n.countQueryOne : i18n.countQueryMany
					),
					strong,
					escapeHtml( q )
			  )
			: format(
					escapeHtml( n === 1 ? i18n.countOne : i18n.countMany ),
					strong
			  );
		if ( topicName ) {
			label +=
				' ' +
				escapeHtml( format( i18n.inTopic, topicName ) ) +
				` <button type="button" class="soli-faq-search__clear-topic">${ escapeHtml(
					i18n.showAll
				) }</button>`;
		}
		return label;
	}

	function render() {
		const matches = filter( data.items, query );
		const hits = topic
			? matches.filter( ( item ) => item.topics.includes( topic ) )
			: matches;

		topicLinks.forEach( ( link ) => {
			const slug = link.dataset.topic;
			const n = slug
				? matches.filter( ( item ) => item.topics.includes( slug ) )
						.length
				: matches.length;
			link.querySelector( '.soli-faq-search__topic-count' ).textContent =
				n.toLocaleString();
			if ( slug === topic ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
			if ( ! n && slug !== topic ) {
				link.setAttribute( 'aria-disabled', 'true' );
			} else {
				link.removeAttribute( 'aria-disabled' );
			}
		} );

		count.innerHTML = countLabel( hits.length, topicNames[ topic ] );

		if ( ! hits.length ) {
			results.innerHTML = `<li class="soli-faq-search__empty">
				<h2 class="soli-faq-search__empty-title">${ escapeHtml(
					format( i18n.emptyTitle, query.trim() )
				) }</h2>
				<p class="soli-faq-search__empty-text">${ escapeHtml(
					i18n.emptyText
				) }</p></li>`;
			return;
		}

		results.innerHTML = hits
			.map( ( item ) => {
				const url = escapeHtml( item.url );
				const topicsHtml = item.topics
					.filter( ( slug ) => topicNames[ slug ] )
					.map(
						( slug ) =>
							`<span>${ escapeHtml( topicNames[ slug ] ) }</span>`
					)
					.join( '' );
				return `<li class="soli-faq-search__result">
					<h2 class="soli-faq-search__title"><a href="${ url }">${ highlight(
						item.title,
						query
					) }</a></h2>
					<p class="soli-faq-search__snippet">${ snippet( item, query ) }</p>
					<div class="soli-faq-search__meta">${ topicsHtml }<span>${ escapeHtml(
						format( i18n.updated, item.updated )
					) }</span><a class="soli-faq-search__more" href="${ url }" tabindex="-1" aria-hidden="true">${ escapeHtml(
						i18n.readAnswer
					) } <span>→</span></a></div>
				</li>`;
			} )
			.join( '' );
	}

	input.addEventListener( 'input', () => {
		query = input.value;
		render();
		syncUrl();
	} );

	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		query = input.value;
		render();
		syncUrl();
	} );

	root.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( '.soli-faq-search__topic' );
		const clear = event.target.closest( '.soli-faq-search__clear-topic' );
		if ( link ) {
			event.preventDefault();
			if ( link.getAttribute( 'aria-disabled' ) === 'true' ) {
				return;
			}
			topic = link.dataset.topic;
		} else if ( clear ) {
			topic = '';
		} else {
			return;
		}
		form.querySelector( 'input[type="hidden"]' )?.remove();
		render();
		syncUrl();
	} );

	// The server list has no highlights; redraw once when arriving with a query.
	if ( termsOf( query ).length || topic ) {
		render();
	}
}

document.querySelectorAll( '[data-soli-faq-search]' ).forEach( init );
