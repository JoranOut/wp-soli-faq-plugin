# Soli FAQ Plugin

Members-only frequently asked questions (FAQs) post type for Soli sites.

~Current Version:0.1.0~

~Plugin Name: wp-soli-faq-plugin~

## Description

Registers the `soli_faq` custom post type for frequently asked questions that are
only visible to logged-in members:

- Query Loop blocks and other front-end queries show nothing to logged-out visitors
- Direct visits to a single FAQ or the archive respond with HTTP 403
- The REST endpoints require a logged-in user

The plugin adds a preconfigured **FAQs** variation of the core Query
Loop block, so editors can drop the latest FAQs on any (members) page.

## Features

- `soli_faq` custom post type (title, editor, excerpt, featured image, revisions)
- Members-only visibility on every surface: queries, single page, archive, search, REST
- "FAQs" Query Loop block variation
- Translations for `nl_NL` and `en_US`
- Automatic updates via GitHub releases

## Requirements

- WordPress 6.0+
- PHP 8.2+
