import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { waitForPageImages } from './support/images';

async function expectNoHorizontalOverflow(page: Page) {
    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));

    expect(width.scroll).toBeLessThanOrEqual(width.client);
}

async function signInAsLeader(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('morgan.leader@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
}

test('public walk leader profile remains accessible and visually stable', async ({ page }, testInfo) => {
    await page.goto('/leaders/morgan-w');
    await expect(page.getByRole('heading', { name: 'Morgan W.' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Upcoming walks' })).toBeVisible();
    await waitForPageImages(page);

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });
    await expectNoHorizontalOverflow(page);
    await expect(page).toHaveScreenshot(`leader-profile-${testInfo.project.name}.png`, { fullPage: true });
});

test('Leader Hub remains actor-owned, keyboard reachable and visually stable', async ({ page }, testInfo) => {
    await signInAsLeader(page);
    await page.goto('/leader-hub');
    await expect(page.getByRole('heading', { name: 'Leader Hub' })).toBeVisible();
    await expect(page.getByText('Check the route access before leaving.').first()).toBeVisible();
    await waitForPageImages(page);

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await page.getByRole('link', { name: 'Leader profile' }).focus();
    await expect(page.getByRole('link', { name: 'Leader profile' })).toBeFocused();
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });
    await expectNoHorizontalOverflow(page);
    await expect(page).toHaveScreenshot(`leader-hub-${testInfo.project.name}.png`, { fullPage: true });
});
