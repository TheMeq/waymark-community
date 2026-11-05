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

test('published walks have a browseable list and public detail page', async ({ page }) => {
    await page.goto('/walks');

    await expect(page.getByRole('heading', { name: 'Walks', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ridge and reservoir' })).toBeVisible();
    await page.getByRole('link', { name: 'Ridge and reservoir' }).click();

    await expect(page.getByRole('heading', { name: 'Ridge and reservoir' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Meeting point' })).toBeVisible();
});

test('public walk list and detail preserve the approved responsive rhythm', async ({ page }, testInfo) => {
    await page.goto('/walks');
    await expect(page.getByRole('link', { name: 'Ridge and reservoir' })).toBeVisible();
    await waitForPageImages(page);
    await expect(page).toHaveScreenshot(`walk-list-${testInfo.project.name}.png`, { fullPage: true });

    await page.getByRole('link', { name: 'Ridge and reservoir' }).click();
    await expect(page.getByRole('heading', { name: 'Ridge and reservoir' })).toBeVisible();
    await waitForPageImages(page);
    await expect(page).toHaveScreenshot(`walk-detail-${testInfo.project.name}.png`, { fullPage: true });
});

test('public walk filter controls retain a visible accessible control surface', async ({ page }) => {
    await page.goto('/walks');
    await page.locator('#walk-filters > summary').click();

    for (const label of [
        'From date',
        'To date',
        'Minimum distance',
        'Maximum distance',
        'Minimum ascent',
        'Maximum ascent',
        'Location',
        'Time',
        'Difficulty',
        'Leader',
    ]) {
        const presentation = await page.getByLabel(label, { exact: true }).evaluate((control) => {
            const style = getComputedStyle(control);

            return {
                backgroundColor: style.backgroundColor,
                borderStyle: style.borderTopStyle,
                borderWidth: Number.parseFloat(style.borderTopWidth),
                height: control.getBoundingClientRect().height,
                paddingInline: Number.parseFloat(style.paddingInlineStart) + Number.parseFloat(style.paddingInlineEnd),
            };
        });

        expect(presentation.height, `${label} minimum height`).toBeGreaterThanOrEqual(44);
        expect(presentation.borderStyle, `${label} border style`).toBe('solid');
        expect(presentation.borderWidth, `${label} border width`).toBeGreaterThanOrEqual(1);
        expect(presentation.backgroundColor, `${label} background`).not.toBe('rgba(0, 0, 0, 0)');
        expect(presentation.paddingInline, `${label} horizontal padding`).toBeGreaterThanOrEqual(16);
    }
});

test('walk list and detail have no detectable WCAG A or AA violations, support keyboard filtering, and remain within the viewport at 200 percent text size', async ({ page }) => {
    await page.goto('/walks');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);

    await page.locator('#walk-filters > summary').focus();
    await page.keyboard.press('Enter');
    await page.getByLabel('Location').focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Time')).toBeFocused();

    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });

    await expectNoHorizontalOverflow(page);

    await page.goto('/walks/ridge-and-reservoir');

    const detailResults = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(detailResults.violations).toEqual([]);

    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });

    await expectNoHorizontalOverflow(page);
});


test.describe('walk filters without JavaScript', () => {
    test.use({ javaScriptEnabled: false });

    test('filters start closed, open with the keyboard, and retain active choices', async ({ page }) => {
        await page.goto('/walks');
        const panel = page.locator('#walk-filters');
        await expect(panel).not.toHaveAttribute('open');
        await expect(page.getByLabel('Location', { exact: true })).toBeHidden();
        await panel.locator('summary').focus();
        await page.keyboard.press('Enter');
        await expect(page.getByLabel('Location', { exact: true })).toBeVisible();
        await page.getByLabel('Location', { exact: true }).fill('Example meeting point');
        await page.getByRole('button', { name: 'Apply filters' }).press('Enter');
        await expect(page).toHaveURL(/location=Example/);
        await expect(panel).not.toHaveAttribute('open');
        await expect(panel.locator('summary')).toContainText('Filters applied');
        await panel.locator('summary').press('Enter');
        await expect(page.getByLabel('Location', { exact: true })).toHaveValue('Example meeting point');
        await page.getByRole('link', { name: 'Clear', exact: true }).press('Enter');
        await expect(panel.locator('summary')).not.toContainText('Filters applied');
    });
});
