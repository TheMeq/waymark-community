import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('morgan.leader@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
}

test('authorised moderation workflow is operable and accessible at every review viewport', async ({ page }) => {
    await signIn(page);
    await page.goto('/admin/photo-moderation');

    await expect(page.getByRole('heading', { name: 'Photo moderation' })).toBeVisible();
    await expect(page.getByText('Browser moderation photo 1', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Approve' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Reject' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Rotate' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Edit' }).first()).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Published photos' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Feature' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Remove' })).toBeVisible();
    await expect(page.locator('nav').filter({ hasText: 'Next' }).first()).toBeVisible();

    await page.getByRole('button', { name: 'Rotate' }).first().focus();
    await expect(page.getByRole('button', { name: 'Rotate' }).first()).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.getByText('Photo approved')).toHaveCount(0);

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);

    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));
    expect(width.scroll).toBeLessThanOrEqual(width.client);
});
