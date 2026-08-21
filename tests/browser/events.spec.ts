import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { waitForPageImages } from './support/images';

const pages = [
    { path: '/socials', heading: 'Upcoming socials', snapshot: 'social-list' },
    { path: '/socials/summer-evening-supper', heading: 'Summer evening supper', snapshot: 'social-detail' },
    { path: '/weekends', heading: 'Weekends away', snapshot: 'holiday-list' },
    { path: '/weekends/coast-and-moor-long-weekend', heading: 'Coast and moor long weekend', snapshot: 'holiday-detail' },
    { path: '/whats-on', heading: "What's on", snapshot: 'whats-on-list' },
    { path: '/whats-on/calendar?month=2026-09', heading: 'September 2026', snapshot: 'whats-on-calendar' },
] as const;

async function expectNoDocumentOverflow(page: Page, path: string) {
    const width = await page.evaluate(() => ({
        client: document.documentElement.clientWidth,
        scroll: document.documentElement.scrollWidth,
    }));
    expect(width.scroll, path).toBeLessThanOrEqual(width.client);
}

test('socials and holidays expose browseable public list and detail pages', async ({ page }) => {
    await page.goto('/socials');
    await page.getByRole('link', { name: 'Summer evening supper' }).click();
    await expect(page.getByRole('heading', { name: 'Summer evening supper' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Booking details' })).toBeVisible();

    await page.goto('/weekends');
    await page.getByRole('link', { name: 'Coast and moor long weekend' }).click();
    await expect(page.getByRole('heading', { name: 'Coast and moor long weekend' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Itinerary' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Clifftop circuit' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Saturday lodge supper' })).toBeVisible();
});

test('combined list and month calendar expose the same event identities', async ({ page }) => {
    await page.goto('/whats-on');
    await expect(page.getByRole('link', { name: 'Summer evening supper' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Coast and moor long weekend' })).toBeVisible();

    await page.goto('/whats-on/calendar?month=2026-09');
    await expect(page.getByRole('link', { name: /Coast and moor long weekend/ }).first()).toBeVisible();
    await expect(page.getByText('16:00 · Holiday', { exact: true })).toBeVisible();
    await expect(page.getByText('Holiday · continues', { exact: true })).toHaveCount(2);
    await expect(page.getByText('Holiday · until 10:00', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: /Clifftop circuit/ })).toBeVisible();
    await expect(page.getByRole('link', { name: /Saturday lodge supper/ })).toBeVisible();
    await expect(page.getByRole('link', { name: 'View September events as a list' }).first()).toBeVisible();
});

test('new Phase 4 public pages preserve responsive visual baselines', async ({ page }, testInfo) => {
    for (const target of pages) {
        await page.goto(target.path);
        await expect(page.getByRole('heading', { name: target.heading, exact: true })).toBeVisible();
        await waitForPageImages(page);
        await expect(page).toHaveScreenshot(`${target.snapshot}-${testInfo.project.name}.png`, { fullPage: true });
    }
});

test('new Phase 4 public pages pass axe and remain inside the viewport at 200 percent text', async ({ page }) => {
    for (const target of pages) {
        await page.goto(target.path);
        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
            .analyze();
        expect(results.violations, target.path).toEqual([]);

        await page.evaluate(() => {
            document.documentElement.style.fontSize = '200%';
        });
        await expectNoDocumentOverflow(page, target.path);
    }
});

test('calendar navigation and events are keyboard operable', async ({ page }) => {
    await page.goto('/whats-on/calendar?month=2026-09');
    await page.getByRole('link', { name: 'Previous month' }).focus();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Next month' })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Scrollable month calendar')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: /Coast and moor long weekend/ }).first()).toBeFocused();
});
