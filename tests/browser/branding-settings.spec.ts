import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function signIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('media.admin@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([page.waitForURL('**/new-here'), page.getByRole('button', { name: 'Sign in' }).click()]);
}

test('branding preview uses unsaved admin values at each representative viewport', async ({ page }, testInfo) => {
    await signIn(page);
    await page.goto('/admin/branding');
    await expect(page.getByRole('heading', { name: 'Branding' })).toBeVisible();

    await page.getByLabel('Group name').fill('Unsaved Pathfinders');
    await page.getByRole('button', { name: 'Preview branding' }).click();

    const previewRegion = page.getByRole('region', { name: 'Unsaved preview' });
    await expect(previewRegion).toBeVisible();
    for (const viewport of ['desktop', 'tablet', 'mobile']) {
        await expect(previewRegion.getByRole('link', { name: new RegExp(`^${viewport}$`, 'i') })).toHaveAttribute('href', new RegExp(`viewport=${viewport}`));
    }
    await expect(page).toHaveScreenshot(`branding-settings-${testInfo.project.name}.png`, { fullPage: true });

    const viewport = testInfo.project.name;
    const previewUrl = await previewRegion.getByRole('link', { name: new RegExp(`^${viewport}$`, 'i') }).getAttribute('href');
    expect(previewUrl).not.toBeNull();
    await page.goto(previewUrl!);
    await expect(page.getByText(`${viewport[0].toUpperCase()}${viewport.slice(1)} preview`)).toBeVisible();
    await expect(page.getByText('Unsaved Pathfinders').first()).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`branding-preview-${testInfo.project.name}.png`, { fullPage: true });

    await page.goto('/');
    await expect(page.getByText('Unsaved Pathfinders')).toHaveCount(0);
});
