import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('authenticated account profile settings are accessible and visually stable', async ({ page }, testInfo) => {
    const email = `profile-${testInfo.project.name}@example.test`;

    await page.goto('/register');
    await page.getByLabel('Your name').fill('Alex Walker');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByLabel('Confirm password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Create account' }).click(),
    ]);

    await page.goto('/account/profile');
    await expect(page.getByRole('heading', { name: 'Profile settings' })).toBeVisible();
    await expect(page.getByText('Alex W.')).toBeVisible();
    await expect(page.getByText('Essential account and security messages are always sent.')).toBeVisible();
    await expect(page.locator('input[type="file"]')).toHaveCount(0);

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`account-profile-${testInfo.project.name}.png`, {
        fullPage: true,
    });
});
