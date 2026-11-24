const { test, expect } = require('@playwright/test');
const { seedFixtures, loginAsAdmin, getRestNonce } = require('./helpers');

test.beforeAll(() => {
	seedFixtures();
});

test.describe('FAQs post type', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('shows the FAQs menu in wp-admin', async ({ page }) => {
		await page.goto('/wp-admin/');
		await expect(page.locator('#menu-posts-soli_faq')).toBeVisible();
	});

	test('lists the seeded FAQ in the admin list table', async ({ page }) => {
		await page.goto('/wp-admin/edit.php?post_type=soli_faq');
		await expect(page.locator('.wp-heading-inline')).toHaveText('FAQs');
		await expect(
			page.getByRole('link', { name: 'Geheime FAQ voor leden' }).first()
		).toBeVisible();
	});

	test('registers the FAQs Query Loop variation in the editor', async ({ page }) => {
		await page.goto('/wp-admin/post-new.php?post_type=page');

		await page.waitForFunction(
			() =>
				window.wp?.blocks
					?.getBlockVariations('core/query')
					?.some((v) => v.name === 'soli-faq/faq-loop'),
			undefined,
			{ timeout: 30000 }
		);
	});

	test('stores the members-only toggle from the editor sidebar', async ({ page }) => {
		await page.goto('/wp-admin/post-new.php?post_type=soli_faq');
		await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());
		// A fresh user gets the welcome guide modal, which hides the sidebar from role queries.
		await page.evaluate(() =>
			window.wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false)
		);
		await expect(page.getByRole('dialog', { name: /Welcome to the editor/ })).toHaveCount(0);

		const toggle = page.getByRole('checkbox', { name: 'Members only' });
		await expect(toggle).toBeChecked();
		await toggle.uncheck();
		await expect(page.getByText('Everyone can read this question')).toBeVisible();

		const postId = await page.evaluate(async () => {
			const { dispatch, select } = window.wp.data;
			await dispatch('core/editor').editPost({ title: 'Publieke vraag uit de editor' });
			await dispatch('core/editor').savePost();
			return select('core/editor').getCurrentPostId();
		});

		const nonce = await getRestNonce(page);
		const headers = { 'X-WP-Nonce': nonce };
		const saved = await page.request.get(`/?rest_route=/wp/v2/faqs/${postId}&context=edit`, { headers });
		expect((await saved.json()).meta.soli_faq_public).toBe(true);

		await page.request.delete(`/?rest_route=/wp/v2/faqs/${postId}`, { headers, data: { force: true } });
	});
});
