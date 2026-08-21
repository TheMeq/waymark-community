import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('account privacy lifecycle controls are accessible and visually stable', async ({ page }, testInfo) => {
    const email = `privacy-${testInfo.project.name}@example.test`;

    await page.goto('/register');
    await page.getByLabel('Your name').fill('Alex Walker');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByLabel('Confirm password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Create account' }).click(),
    ]);

    await page.goto('/account/privacy');
    await expect(page.getByRole('heading', { name: 'Privacy and account lifecycle' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Request data export' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Request deletion' })).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`account-privacy-${testInfo.project.name}.png`, { fullPage: true });

    await page.evaluate(() => {
        document.body.style.zoom = '2';
    });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
