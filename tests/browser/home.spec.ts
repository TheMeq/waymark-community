import { expect, test } from '@playwright/test';
import { waitForPageImages } from './support/images';

test('homepage matches the approved responsive visual foundation', async ({ page }, testInfo) => {
    await page.goto('/');
    const essentialConsent = page.getByRole('button', { name: 'Use essential only' });
    if (testInfo.project.name !== 'desktop' && await essentialConsent.isVisible()) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }),
            essentialConsent.click(),
        ]);
    }
    await waitForPageImages(page);

    await expect(page).toHaveScreenshot(`homepage-${testInfo.project.name}.png`, {
        fullPage: true,
    });
});

test('configured homepage layout variants materially change responsive composition', async ({ page }, testInfo) => {
    await page.goto('/');
    await expect(page.locator('[data-homepage-section="hero"]')).toBeVisible();
    await waitForPageImages(page);

    const box = async (selector: string) => {
        const bounds = await page.locator(selector).boundingBox();
        expect(bounds, `${selector} should be rendered`).not.toBeNull();

        return bounds!;
    };

    const heroSection = page.locator('[data-homepage-section="hero"]');
    const compactHero = await box('[data-homepage-section="hero"]');
    await heroSection.evaluate((section) => {
        section.classList.replace('wm-home-layout-compact', 'wm-home-layout-default');
    });
    const defaultHero = await box('[data-homepage-section="hero"]');
    expect(compactHero.height).toBeLessThan(defaultHero.height - 30);

    const memories = page.locator('[data-homepage-memory]');
    const featuredMemory = await memories.nth(0).boundingBox();
    const supportingMemory = await memories.nth(1).boundingBox();
    expect(featuredMemory).not.toBeNull();
    expect(supportingMemory).not.toBeNull();
    expect(featuredMemory!.width).toBeGreaterThan(supportingMemory!.width * 1.5);
    expect(featuredMemory!.height).toBeGreaterThan(supportingMemory!.height);

    const joinPanel = await box('[data-homepage-join-panel]');
    const resources = await box('[data-homepage-member-resources]');
    const joinPrecedesResources = await page.locator('[data-homepage-section="join"]').evaluate((section) => {
        const panel = section.querySelector('[data-homepage-join-panel]');
        const memberResources = section.querySelector('[data-homepage-member-resources]');

        return Boolean(panel && memberResources && (panel.compareDocumentPosition(memberResources) & Node.DOCUMENT_POSITION_FOLLOWING));
    });
    expect(joinPrecedesResources).toBe(true);
    if (testInfo.project.name === 'mobile') {
        expect(joinPanel.y).toBeLessThan(resources.y);
    } else {
        expect(resources.x).toBeLessThan(joinPanel.x);
    }

    const holidayImage = await box('[data-homepage-holiday-image]');
    const holidayContent = await box('[data-homepage-holiday-content]');
    if (testInfo.project.name === 'mobile') {
        expect(holidayImage.y).toBeLessThan(holidayContent.y);
    } else {
        expect(holidayImage.width).toBeGreaterThan(holidayContent.width * 1.55);
    }

    const newsCards = page.locator('[data-homepage-news-card]');
    const featuredNews = await newsCards.nth(0).boundingBox();
    const standardNews = await newsCards.nth(1).boundingBox();
    expect(featuredNews).not.toBeNull();
    expect(standardNews).not.toBeNull();
    if (testInfo.project.name === 'mobile') {
        expect(Math.abs(featuredNews!.width - standardNews!.width)).toBeLessThan(2);
    } else {
        expect(featuredNews!.width).toBeGreaterThan(standardNews!.width * 1.5);
    }
});

test('desktop homepage keeps a restrained full-composition vertical rhythm', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop');

    await page.goto('/');
    await waitForPageImages(page);

    const pageHeight = await page.evaluate(() => document.documentElement.scrollHeight);

    expect(pageHeight).toBeLessThanOrEqual(2400);
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
