const { test, expect } = require('@playwright/test');
const { seedFixtures, loginAsAdmin } = require('./helpers');

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
});
