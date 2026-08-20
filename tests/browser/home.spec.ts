import { expect, test, type Page } from '@playwright/test';

async function waitForImages(page: Page) {
    await page.locator('img').evaluateAll(async (images: HTMLImageElement[]) => {
        await Promise.all(images.map(async (image) => {
            if (image.complete) {
                return;
            }

            await new Promise<void>((resolve) => {
                image.addEventListener('load', () => resolve(), { once: true });
                image.addEventListener('error', () => resolve(), { once: true });
            });
        }));
    });
}

test('homepage matches the approved responsive visual foundation', async ({ page }, testInfo) => {
    await page.goto('/');
    await waitForImages(page);

    await expect(page).toHaveScreenshot(`homepage-${testInfo.project.name}.png`, {
        fullPage: true,
    });
});

test('mobile menu is keyboard operable', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile');

    await page.goto('/');
    const menuToggle = page.getByLabel('Open navigation');

    await menuToggle.press('Enter');

    await expect(page.getByRole('navigation', { name: 'Mobile navigation' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Upcoming walks' }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Join us' }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Account' }).first()).toBeVisible();
});

test('site banner dismissal is remembered for its version', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop');

    await page.goto('/_dev/components');
    await page.evaluate(() => window.localStorage.clear());
    await page.reload();

    const banner = page.getByLabel('Site announcement');
    await expect(banner).toBeVisible();
    await page.getByRole('button', { name: 'Dismiss announcement' }).click();
    await expect(banner).toBeHidden();

    await page.reload();
    await expect(banner).toBeHidden();
});
