import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

async function signIn(page: Page, email = 'media.admin@example.test') {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
}

async function expectAccessible(page: Page) {
    const accessibility = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(accessibility.violations).toEqual([]);
}

test('task-focused dashboard and sidebar work responsively', async ({ page }, testInfo) => {
    await signIn(page);
    await page.goto('/admin');

    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Getting started' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Dismiss' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Add a walk' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Manage photos' })).toBeVisible();
    await expect(page.getByText('Upcoming events')).toBeVisible();
    await expect(page.getByRole('link', { name: 'GitHub' })).toHaveCount(0);

    const screenshotDirectory = resolve('test-results/post-install/screenshots');
    await mkdir(screenshotDirectory, { recursive: true });
    await page.screenshot({
        path: resolve(screenshotDirectory, `admin-dashboard-${testInfo.project.name}.png`),
        fullPage: true,
    });

    if (testInfo.project.name !== 'desktop') {
        await page.getByRole('button', { name: 'Expand sidebar' }).click();
    }
    await expect(page.getByText('Events', { exact: true })).toBeVisible();
    await expect(page.getByText('Community', { exact: true })).toBeVisible();
    await expect(page.getByText('Group settings', { exact: true })).toBeVisible();
    await expect(page.getByText('System', { exact: true })).toBeVisible();
    await expectAccessible(page);
    await page.screenshot({
        path: resolve(screenshotDirectory, `admin-sidebar-${testInfo.project.name}.png`),
        fullPage: testInfo.project.name === 'desktop',
    });
});

test('add walk wizard preserves data through Back and Next without overflow at 200 percent text', async ({ page }, testInfo) => {
    await signIn(page);
    await page.goto('/admin/walks/create');

    await expect(page.getByText('When and where', { exact: true })).toBeVisible();
    await page.getByLabel('Title').fill('Accessible browser walk');
    await page.getByLabel('Starts at').fill('2026-09-10T09:30');
    await page.getByRole('button', { name: 'Next' }).click();
    await expect(page.getByText('Walk details', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Back' }).click();
    await expect(page.getByLabel('Title')).toHaveValue('Accessible browser walk');

    await page.evaluate(() => {
        document.documentElement.style.fontSize = '200%';
    });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => document.activeElement?.tagName)).not.toBe('BODY');
    await expectAccessible(page);

    const screenshotDirectory = resolve('test-results/post-install/screenshots');
    await mkdir(screenshotDirectory, { recursive: true });
    await page.screenshot({
        path: resolve(screenshotDirectory, `add-walk-wizard-${testInfo.project.name}.png`),
        fullPage: true,
    });
});

test('walk leader completes all five Add Walk steps through the dashboard action', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The existing responsive wizard test covers tablet and mobile presentation.');

    await signIn(page, 'walk.wizard@example.test');
    await page.goto('/admin');

    const addWalk = page.getByRole('link', { name: 'Add a walk' });
    await expect(addWalk).toBeVisible();
    await addWalk.click();
    await expect(page).toHaveURL(/\/admin\/walks\/create$/);
    await expect(page.getByText('When and where', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Title')).toBeVisible();

    await page.getByLabel('Title').fill('Five step browser walk');
    await page.getByLabel('Starts at').fill('2026-10-03T09:30');
    await page.getByRole('button', { name: 'Next' }).click();
    await expect(page.getByText('Draft saved', { exact: true })).toBeVisible();
    await expect(page).toHaveURL((url) => (
        url.pathname === '/admin/walks/create'
        && /^\d+$/.test(url.searchParams.get('draft') ?? '')
        && url.searchParams.get('step') === 'walk-details'
    ));
    await expect(page.getByText('Walk details', { exact: true })).toBeVisible();

    await page.reload();
    await expect(page.getByText('Walk details', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Back' }).click();
    await expect(page.getByLabel('Title')).toHaveValue('Five step browser walk');
    await expect(page.getByLabel('Starts at')).toHaveValue('2026-10-03T09:30');
    await page.getByRole('button', { name: 'Next' }).click();

    const gradeField = page.locator('[data-field-wrapper]').filter({
        has: page.getByText('Grade', { exact: true }),
    });
    const createGrade = gradeField.getByRole('button', { name: 'Create' });
    await createGrade.click();
    const createGradeDialog = page.getByRole('dialog').filter({
        has: page.getByRole('heading', { name: 'Create grade' }),
    });
    await expect(createGradeDialog).toHaveClass(/fi-modal-open/);
    await expect(createGradeDialog.getByRole('heading', { name: 'Create grade' })).toBeVisible();
    await createGradeDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(createGrade).toBeFocused();

    await page.getByLabel('Distance').fill('7.5');
    await page.getByRole('button', { name: 'Next' }).click();
    await expect(page.getByText('Travel and practical information', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Next' }).click();
    await expect(page.getByText('Description, route and image', { exact: true })).toBeVisible();
    await page.getByLabel('Summary').fill('A complete browser journey through the existing walk workflow.');

    await page.getByRole('button', { name: 'Next' }).click();
    await expect(page.getByText('Leader and publishing', { exact: true })).toBeVisible();
    await page.getByLabel('Primary leader').click();
    await page.getByRole('option', { name: 'Taylor Walker' }).click();

    await page.getByRole('button', { name: 'Submit walk' }).click();
    await expect(page).toHaveURL(/\/admin\/walks\/\d+\/edit$/);
    await expect(page.getByLabel('Title')).toHaveValue('Five step browser walk');
    await expect(page.getByLabel('Distance')).toHaveValue('7.5');
    await expect(page.getByLabel('Summary')).toHaveValue('A complete browser journey through the existing walk workflow.');

    await page.goto('/admin/walks');
    await expect(page.getByRole('row', { name: /Five step browser walk.*pending_approval.*Taylor Walker/i })).toBeVisible();
    await expect(page.getByText('Browser moderation walk')).toHaveCount(0);
});

test('persists a managed Walk image through draft resume', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'One desktop browser flow proves the critical upload checkpoint contract.');

    await signIn(page, 'walk.wizard@example.test');
    await page.goto('/admin/walks/create');
    await page.getByLabel('Title').fill('Managed image browser walk');
    await page.getByLabel('Starts at').fill('2026-10-10T09:30');
    await page.getByRole('button', { name: 'Next' }).click();
    await page.getByRole('button', { name: 'Next' }).click();
    await page.getByRole('button', { name: 'Next' }).click();

    await expect(page.getByText('Description, route and image', { exact: true })).toBeVisible();
    await expect(page.locator('[id="form.featured_image_upload"]')).toBeVisible();
    await page.locator('[id="form.featured_image_upload"] input[type="file"]').setInputFiles(resolve('public/images/demo/woodland-walk-768.webp'));
    await page.getByLabel('Image description').fill('Walkers following a sunlit woodland path');
    await expect(page.getByText('Temporary preview selected. Your image will be saved when you continue from Step 4.')).toBeVisible();
    await expect(page.locator('[data-testid="managed-image-preview"]')).toHaveCount(0);

    await page.getByRole('button', { name: 'Next' }).click();
    await expect(page.getByText('Draft saved', { exact: true })).toBeVisible();
    const draftUrl = new URL(page.url());
    draftUrl.searchParams.set('step', 'description-route-and-image');

    await page.goto('/admin/walks');
    await page.goto(draftUrl.toString());
    await expect(page.getByText('Description, route and image', { exact: true })).toBeVisible();
    await expect(page.locator('[data-testid="managed-image-preview"] img')).toHaveAttribute('alt', 'Walkers following a sunlit woodland path');
    await expect(page.getByLabel('Image description')).toHaveValue('Walkers following a sunlit woodland path');
    await expect(page.locator('[data-testid="managed-image-status"]')).toHaveAttribute('role', 'status');
    await expect(page.locator('[data-testid="managed-image-status"]')).toHaveAttribute('aria-live', 'polite');
    await expectAccessible(page);
});
