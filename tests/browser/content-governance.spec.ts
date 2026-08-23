import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { waitForPageImages } from './support/images';

const pages = [
    { name: 'cms-page', path: '/pages/walking-with-us', heading: 'Walking with us' },
    { name: 'news-detail', path: '/news/paths-people-late-summer-plans', heading: 'Paths, people and late-summer plans' },
    { name: 'document-detail', path: '/documents/walking-guide', heading: 'Walking guide' },
];

for (const publicPage of pages) {
    test(`${publicPage.name} is responsive and meets the public accessibility gate`, async ({ page }, testInfo) => {
        await page.goto(publicPage.path);
        await expect(page.getByRole('heading', { level: 1, name: publicPage.heading })).toBeVisible();
        await waitForPageImages(page);

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
            .analyze();
        expect(results.violations).toEqual([]);
        await expect(page).toHaveScreenshot(`phase-07-${publicPage.name}-${testInfo.project.name}.png`, { fullPage: true });

        await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
        const width = await page.evaluate(() => ({ client: document.documentElement.clientWidth, scroll: document.documentElement.scrollWidth }));
        expect(width.scroll).toBeLessThanOrEqual(width.client);
    });
}
