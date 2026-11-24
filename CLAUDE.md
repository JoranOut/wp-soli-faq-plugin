# wp-soli-faq-plugin

Members-only frequently asked questions (FAQs) post type for Soli sites.

## Purpose

1. **Post type** — registers `soli_faq` (rewrite slug `faq`), a plain
   CPT with title, editor, excerpt, featured image and revisions. No custom tables,
   no options, no migrations.
2. **Members-only by default** — a logged-out visitor never sees a members-only
   FAQ, on any surface. Editors can mark a FAQ public with the "Members only"
   toggle in the editor sidebar (post meta `soli_faq_public`). Any logged-in user
   (any role) sees everything published.
3. **Query Loop integration** — `show_in_rest` plus a preconfigured "FAQs"
   variation of `core/query`, so editors drop the latest FAQs on any page.
4. **Search block** — `soli-faq/search` lists every FAQ with a search field and
   a topic filter (`soli_faq_topic` taxonomy).

## Architecture

```
wp-soli-faq-plugin.php               constants, textdomain, GitHub updater
├── includes/
│   ├── class-soli-faq-post-type.php   # CPT, topic taxonomy, public meta, editor script enqueue
│   ├── class-soli-faq-visibility.php  # all logged-out hiding
│   └── class-soli-faq-search.php      # search block registration, data, PHP matching
├── src/index.js  →  build/index.js    # core/query block variation + members-only toggle (src/members-only.js)
└── src/search/   →  build/search/     # soli-faq/search block (render.php, view.js)
```

`webpack.config.js` adds `src/index.js` as an extra entry. Without it wp-scripts
builds only the `block.json` blocks and drops the variation script.

## Visibility policy (authoritative)

All decisions live in `Visibility` (`includes/class-soli-faq-visibility.php`)
plus the `exclude_from_search` flag and `is_public()` in `Post_Type`, and the
public filter in `Search::items()`. Do not re-implement checks elsewhere.

| Surface | Logged out | How |
|---|---|---|
| Query Loop / secondary WP_Query | public FAQs only | `pre_get_posts` adds a `soli_faq_public = 1` meta query; a mixed-type query loses the FAQ post type instead, because the meta query would filter the other types too |
| Single page | members-only: HTTP 403; public: 200 | `template_redirect` guard (`wp_die`), event-plugin pattern |
| Archive | disabled | `has_archive => false`; the pretty URL (`/faq/`) 404s, and a `?post_type=soli_faq` query still hits the same 403 guard |
| Site search | hidden, public ones too | `exclude_from_search => ! is_user_logged_in()` in the CPT registration (per request) |
| REST collection + single | HTTP 403, public ones too | `rest_request_before_callbacks` on `/wp/v2/faqs*` |
| REST topic terms | HTTP 403 | same filter, `/wp/v2/faq-topics*` |
| Topic term archive | none | taxonomy registered with `public => false`, `rewrite => false` |
| Search block | public FAQs plus a login note; only a login prompt when none are public | `Search::items()` adds the same meta query; `render.php` picks prompt or note |

The main query for single/archive is deliberately *not* emptied in `pre_get_posts` —
that would turn the 403 into a silent 404.

A FAQ without the meta counts as members-only, so FAQs from before the toggle
stay hidden. `Search::has_members_only()` queries `$wpdb` directly: a WP_Query in a
logged-out request passes through `pre_get_posts` and would only find public FAQs.

The meta reaches the editor only because the CPT supports `custom-fields`;
`register_post_meta( show_in_rest )` alone is not enough. The toggle sits in
`PluginPostStatusInfo` (the always-visible summary) rather than a
`PluginDocumentSettingPanel`, which starts collapsed.

Note: REST cookie auth without an `X-WP-Nonce` header counts as logged out
(WordPress core behaviour); the block editor always sends the nonce.

## Query Loop variation

`src/index.js` registers `soli-faq/faq-loop` on `core/query`
(post type preset, newest first, post-template with title/date/excerpt,
query-no-results). Built with `wp-scripts build` to `build/index.js`.

**`build/` and the compiled `languages/` files are committed** — `publish.js`
zips what is on disk and the GitHub updater installs that release zip asset,
so shipped artifacts must be in the repo.
Run `npm run build` before committing JS changes.

## Search block

`soli-faq/search` is a dynamic block. `render.php` prints the full result list
for the current URL (`?faq_search=` and `?faq_topic=`), so search and the topic
links work without JavaScript. It also embeds every FAQ as JSON (title, plain
text, excerpt, topics, modified date). `view.js` reads that JSON, filters on each
keystroke and keeps both parameters in the URL with `replaceState`, so the back
button from an answer returns to the same search.

PHP (`Search::filter()`) and JS (`filter()` in `view.js`) use the same rules:
lowercase, accents stripped, every term of two or more characters must occur,
a title hit scores 10 and each body hit 1 (capped at 5). Change both together.

The FAQ text comes from `post_content` with tags stripped, not from
`the_content`, because an FAQ that contains the search block would render itself.

The front-end strings travel in the JSON from PHP, so `view.js` needs no script
translations. The editor script does: `Search::register_block()` points
`wp_set_script_translations` at `/languages`, because block.json registration
only looks in `WP_LANG_DIR`.

Styling uses the theme presets (`--wp--preset--color--maroon` and so on) with the
Soli values as fallback, and container queries on the block itself.

## Development

```bash
npm install
npm run build          # build src/ -> build/
npm run wp-env:start   # localhost:8896 (admin/password), tests on 8897
npm run test:e2e       # Playwright, see /e2e
npm run i18n:build     # pot -> mo -> json (requires running wp-env)
```

E2E fixtures are seeded by `e2e/fixtures/seed.php` via `wp eval-file`
(idempotent), avoiding wp-env argument-quoting issues.

### Coding Standards

- Namespace: `Soli\Faq`
- Function/hook prefix: `soli_faq_`
- Text domain: `soli-faq`
- Constants: `SOLI_FAQ__*`
- Locales: `nl_NL` and `en_US`

### i18n gotcha

`i18n:make-pot` excludes `src/` on purpose: the JS string references must point
at `build/index.js` and `build/search/index.js` so the `make-json` file hashes
match what `wp_set_script_translations` looks up at runtime.

## Versioning & releases

Standard Soli flow (see repo-root CLAUDE.md): version synced in 4 places
(plugin header, `SOLI_FAQ__PLUGIN_VERSION`, readme.md
`~Current Version:x.x.x~`, package.json), release branches, nightly builds,
GitHub updater channels.
