const { test, expect } = require('@playwright/test');
const { seedFixtures, loginAsAdmin, getRestNonce } = require('./helpers');

const PARTY = 'Hoe organiseer ik een feestje of borrel voor mijn orkest?';
const VOLUNTEER = 'Hoe kan ik mij inzetten voor de vereniging?';
const PUBLIC = 'Waar vind ik het Muziekcentrum?';

let fixtures;

test.beforeAll(() => {
	fixtures = seedFixtures();
});

test.describe('FAQ search block for members', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('lists every FAQ before searching', async ({ page }) => {
		await page.goto(fixtures.searchPageUrl);
		const results = page.locator('.soli-faq-search__result');
		await expect(results.filter({ hasText: PARTY })).toHaveCount(1);
		await expect(results.filter({ hasText: VOLUNTEER })).toHaveCount(1);
		await expect(results.filter({ hasText: 'Geheime FAQ voor leden' })).toHaveCount(1);
		await expect(results.filter({ hasText: PUBLIC })).toHaveCount(1);
		await expect(page.locator('.soli-faq-search__login-note')).toHaveCount(0);
	});

	test('filters live, ignores accents and highlights matches', async ({ page }) => {
		await page.goto(fixtures.searchPageUrl);
		await page.locator('.soli-faq-search__input').fill('hygiene');

		const results = page.locator('.soli-faq-search__result');
		await expect(results).toHaveCount(1);
		await expect(results.first()).toContainText(PARTY);
		await expect(results.first().locator('mark')).toHaveText('Hygiëne');
		await expect(page.locator('.soli-faq-search__count')).toContainText('1');
		await expect(page).toHaveURL(/faq_search=hygiene/);
	});

	test('ranks a title match above a body match', async ({ page }) => {
		await page.goto(fixtures.searchPageUrl);
		// "feest" is in the party title and only in the body of the volunteer FAQ.
		await page.locator('.soli-faq-search__input').fill('feest');
		const titles = page.locator('.soli-faq-search__title');
		await expect(titles).toHaveCount(2);
		await expect(titles.first()).toContainText(PARTY);
		await expect(titles.nth(1)).toContainText(VOLUNTEER);
	});

	test('narrows to a topic and can show all topics again', async ({ page }) => {
		await page.goto(fixtures.searchPageUrl);
		await page.locator('.soli-faq-search__topic', { hasText: 'Vrijwilligers' }).click();

		await expect(page.locator('.soli-faq-search__result')).toHaveCount(1);
		await expect(page.locator('.soli-faq-search__result')).toContainText(VOLUNTEER);
		await expect(page).toHaveURL(/faq_topic=vrijwilligers/);

		await page.locator('.soli-faq-search__clear-topic').click();
		await expect(page.locator('.soli-faq-search__result')).toHaveCount(4);
	});

	test('shows an empty state for a word nothing contains', async ({ page }) => {
		await page.goto(fixtures.searchPageUrl);
		await page.locator('.soli-faq-search__input').fill('zwembad');
		await expect(page.locator('.soli-faq-search__result')).toHaveCount(0);
		await expect(page.locator('.soli-faq-search__empty-title')).toContainText('zwembad');
	});

	test('filters on the server when JavaScript is off', async ({ browser }) => {
		const context = await browser.newContext({ javaScriptEnabled: false });
		const page = await context.newPage();
		await loginAsAdmin(page);

		await page.goto(`${fixtures.searchPageUrl}${fixtures.searchPageUrl.includes('?') ? '&' : '?'}faq_search=borrel`);
		await expect(page.locator('.soli-faq-search__result')).toHaveCount(1);
		await expect(page.locator('.soli-faq-search__result')).toContainText(PARTY);

		await page.locator('.soli-faq-search__topic', { hasText: 'Activiteiten' }).click();
		await expect(page).toHaveURL(/faq_topic=activiteiten/);
		await expect(page.locator('.soli-faq-search__result')).toHaveCount(1);

		await page.goto(`${page.url()}`.replace('faq_search=borrel', 'faq_search=vereniging'));
		await expect(page.locator('.soli-faq-search__empty-title')).toBeVisible();
		await context.close();
	});

	test('reads the topics through REST with a nonce', async ({ page }) => {
		const nonce = await getRestNonce(page);
		const response = await page.request.get('/?rest_route=/wp/v2/faq-topics', {
			headers: { 'X-WP-Nonce': nonce },
		});
		expect(response.status()).toBe(200);
		const slugs = (await response.json()).map((t) => t.slug);
		expect(slugs).toEqual(expect.arrayContaining(['vrijwilligers', 'activiteiten']));
	});
});

test.describe('FAQ search block for logged-out visitors', () => {
	test('lists only public FAQs, with a login note', async ({ page }) => {
		const response = await page.goto(fixtures.searchPageUrl);
		expect(response.status()).toBe(200);

		await expect(page.locator('.soli-faq-search__result')).toHaveCount(1);
		await expect(page.locator('.soli-faq-search__result')).toContainText(PUBLIC);
		await expect(page.locator('.soli-faq-search__login-note a')).toHaveAttribute('href', /wp-login\.php/);
		// Both FAQs with topics are members-only, so no topic is left to filter on.
		await expect(page.locator('.soli-faq-search__topic', { hasText: 'Vrijwilligers' })).toHaveCount(0);

		const html = await page.content();
		expect(html).not.toContain(PARTY);
		expect(html).not.toContain(VOLUNTEER);
		expect(html).not.toContain('Sociale Hygi');
		expect(html).not.toContain('Geheime FAQ voor leden');
	});

	test('get a 403 from the REST topics endpoint', async ({ page }) => {
		const response = await page.request.get('/?rest_route=/wp/v2/faq-topics');
		expect(response.status()).toBe(403);
	});
});
