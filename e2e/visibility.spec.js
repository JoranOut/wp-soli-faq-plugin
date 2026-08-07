const { test, expect } = require('@playwright/test');
const { seedFixtures, loginAsAdmin, getRestNonce } = require('./helpers');

let fixtures;

test.beforeAll(() => {
	fixtures = seedFixtures();
});

test.describe('logged-out visitors', () => {
	test('get a 403 on the single FAQ page', async ({ page }) => {
		const response = await page.goto(fixtures.faqUrl);
		expect(response.status()).toBe(403);
		await expect(page.locator('body')).toContainText('only visible to members');
	});

	test('get a 403 on the FAQs archive', async ({ page }) => {
		const response = await page.goto('/?post_type=soli_faq');
		expect(response.status()).toBe(403);
	});

	test('see no FAQs in a query loop', async ({ page }) => {
		await page.goto(fixtures.pageUrl);
		await expect(page.locator('body')).toContainText('FAQ overzicht');
		await expect(page.locator('body')).not.toContainText('Geheime FAQ voor leden');
	});

	test('get a 403 from the REST collection and single endpoints', async ({ page }) => {
		const collection = await page.request.get('/?rest_route=/wp/v2/faqs');
		expect(collection.status()).toBe(403);

		const single = await page.request.get(
			`/?rest_route=/wp/v2/faqs/${fixtures.faqId}`
		);
		expect(single.status()).toBe(403);
	});

	test('do not find FAQs through site search', async ({ page }) => {
		await page.goto('/?s=Geheime+FAQ');
		await expect(page.locator('body')).not.toContainText('Geheime FAQ voor leden');
	});
});

test.describe('logged-in members', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('can read the single FAQ page', async ({ page }) => {
		const response = await page.goto(fixtures.faqUrl);
		expect(response.status()).toBe(200);
		await expect(page.locator('body')).toContainText('Geheime FAQ voor leden');
		await expect(page.locator('body')).toContainText('Alleen voor ingelogde leden bedoeld.');
	});

	test('see FAQs in a query loop', async ({ page }) => {
		await page.goto(fixtures.pageUrl);
		await expect(
			page.getByRole('link', { name: 'Geheime FAQ voor leden' }).first()
		).toBeVisible();
	});

	test('can read the REST collection with a nonce, like the editor does', async ({ page }) => {
		const nonce = await getRestNonce(page);
		const collection = await page.request.get('/?rest_route=/wp/v2/faqs', {
			headers: { 'X-WP-Nonce': nonce },
		});
		expect(collection.status()).toBe(200);
		const posts = await collection.json();
		expect(posts.some((p) => String(p.id) === String(fixtures.faqId))).toBe(true);
	});
});
