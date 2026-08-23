import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function expectNoHorizontalOverflow(page: Page) {
    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));

    expect(width.scroll).toBeLessThanOrEqual(width.client);
}

async function signIn(page: Page, projectName: string) {
    const email = `favourites-${projectName}@example.test`;

    await page.goto('/register');
    await page.getByLabel('Name').fill(`Favourite ${projectName}`);
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByLabel('Confirm password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Create account' }).click(),
    ]);
}

async function tabTo(page: Page, target: ReturnType<Page['getByRole']>) {
    for (let attempts = 0; attempts < 30; attempts++) {
        await page.keyboard.press('Tab');

        if (await target.evaluate((element) => element === document.activeElement)) {
            return;
        }
    }

    throw new Error('Keyboard focus did not reach the saved-event removal control.');
}

test('an authenticated user can save, list and remove a favourite accessibly', async ({ page }, testInfo) => {
    await signIn(page, testInfo.project.name);
    await page.goto('/walks/ridge-and-reservoir');
    await page.getByRole('button', { name: 'Save to favourites' }).click();
    await expect(page.getByRole('button', { name: 'Remove from favourites' })).toBeVisible();

    await page.goto('/account/favourites');
    await expect(page.getByRole('heading', { name: 'Saved events' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Walks' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ridge and reservoir' })).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`favourites-${testInfo.project.name}.png`, { fullPage: true });
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
    await tabTo(page, page.getByRole('button', { name: 'Remove' }));
    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });
    await expectNoHorizontalOverflow(page);

    await page.getByRole('button', { name: 'Use essential only' }).click();
    await page.getByRole('button', { name: 'Remove' }).click();
    await expect(page.getByText('No saved events yet.')).toBeVisible();
});
