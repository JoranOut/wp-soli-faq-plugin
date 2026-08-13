const { test, expect } = require('@playwright/test');
const { seedFixtures, loginAsAdmin } = require('./helpers');

let fixtures;

test.beforeAll(() => {
	fixtures = seedFixtures();
});

/**
 * WP_DEBUG and WP_DEBUG_DISPLAY are enabled for the tests environment in
 * .wp-env.json, so PHP diagnostics are rendered into the page body.
 *
 * Fatals and parse errors are never acceptable, wherever they come from.
 * Softer diagnostics are only asserted for this plugin's own files, so an
 * unrelated WordPress core deprecation cannot turn CI red.
 */
const FATAL = /Fatal error|Parse error/i;
const OWN_FILES =
	/(Warning|Notice|Deprecated):[^\n]*(wp-soli-faq-plugin\.php|class-soli-faq-(post-type|visibility)\.php)/i;

async function expectNoPhpErrors(page) {
	// textContent, never innerText. innerText reflects *rendered* text, so it
	// silently drops anything inside an element that ships hidden
	// (display:none until JS reveals it) — and the block editor screen is full
	// of those. A diagnostic emitted there would be invisible to innerText
	// and the assertion would pass while the page is broken. textContent
	// reads the DOM regardless of styling. Do not change this back.
	//
	// The two patterns need *different* reads, so take both in one evaluate:
	//
	// - OWN_FILES matches within a single line (`[^\n]*`), and textContent
	//   includes the source text of <script>. wp-admin prints large one-line
	//   JSON blobs into inline script, so a string containing `Warning:` near
	//   a plugin path would match and turn CI red for nothing. That pattern
	//   must NOT see script text, so it reads a body clone with
	//   script/style/template/noscript stripped.
	// - FATAL must see script text: a fatal thrown while an inline script is
	//   being printed lands inside that <script> node, and a stripped clone
	//   would lose it. `Fatal error`/`Parse error` are also far less likely
	//   than `Warning:` to appear in script text by accident.
	const { full, markup } = await page.evaluate(() => {
		const clone = document.body.cloneNode(true);
		clone
			.querySelectorAll('script, style, template, noscript')
			.forEach((node) => node.remove());

		return {
			full: document.body.textContent || '',
			markup: clone.textContent || '',
		};
	});

	expect(full).not.toMatch(FATAL);
	expect(markup).not.toMatch(OWN_FILES);
}

test.describe('renders without PHP errors', () => {
	// Logged in throughout: logged-out visitors get a 403 on every FAQ
	// surface, which would render the error assertions meaningless.
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('on the single FAQ page', async ({ page }) => {
		await page.goto(fixtures.faqUrl);
		await expectNoPhpErrors(page);
	});

	test('on the FAQs archive', async ({ page }) => {
		await page.goto('/?post_type=soli_faq');
		await expectNoPhpErrors(page);
	});

	test('on a page with the FAQs query loop', async ({ page }) => {
		await page.goto(fixtures.pageUrl);
		await expect(page.locator('body')).toContainText('Geheime FAQ voor leden');
		await expectNoPhpErrors(page);
	});

	test('in the wp-admin list table', async ({ page }) => {
		await page.goto('/wp-admin/edit.php?post_type=soli_faq');
		await expectNoPhpErrors(page);
	});

	test('in the block editor for a FAQ', async ({ page }) => {
		await page.goto(`/wp-admin/post.php?post=${fixtures.faqId}&action=edit`);
		await expectNoPhpErrors(page);
	});
});
