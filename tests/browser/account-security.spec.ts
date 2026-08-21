import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { createHmac } from 'node:crypto';
import { errors } from 'playwright';

test('account security settings are accessible and visually stable', async ({ page }, testInfo) => {
    const email = `security-${testInfo.project.name}@example.test`;

    await page.goto('/register');
    await page.getByLabel('Your name').fill('Alex Walker');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByLabel('Confirm password').fill('password');
    await Promise.all([
        page.waitForURL('**/new-here'),
        page.getByRole('button', { name: 'Create account' }).click(),
    ]);

    await page.goto('/account/security');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/account/security'),
        page.getByRole('button', { name: 'Continue' }).click(),
    ]);

    await expect(page.getByRole('heading', { name: 'Account security' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Set up two-factor authentication' })).toBeVisible();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`account-security-${testInfo.project.name}.png`, {
        fullPage: true,
    });

    await page.evaluate(() => {
        document.body.style.zoom = '2';
    });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('sensitive two-factor confirmation is keyboard-accessible and visually stable', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('security-browser@example.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/two-factor-challenge'),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);

    await submitTotpWithRolloverRetry(page, 'JBSWY3DPEHPK3PXP', '**/new-here');

    await page.goto('/account/security');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL('**/account/security'),
        page.getByRole('button', { name: 'Continue' }).click(),
    ]);

    await page.goto('/account/sensitive-confirmation');

    const code = page.getByLabel('Authentication code');
    await code.focus();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Continue' })).toBeFocused();

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze();

    expect(results.violations).toEqual([]);
    await expect(page).toHaveScreenshot(`sensitive-confirmation-${testInfo.project.name}.png`, {
        fullPage: true,
    });

    await page.evaluate(() => {
        document.body.style.zoom = '2';
    });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('TOTP retry only follows a navigation timeout after the counter rolls over', async () => {
    let currentTime = 29_999;
    let submissions = 0;
    const originalDateNow = Date.now;
    Date.now = () => currentTime;

    try {
        const fake = fakeTotpPage(() => {
            submissions++;

            if (submissions === 1) {
                currentTime = 30_000;

                throw new errors.TimeoutError('TOTP navigation timed out.');
            }
        });

        await submitTotpWithRolloverRetry(fake.page, 'JBSWY3DPEHPK3PXP', '**/new-here');

        expect(submissions).toBe(2);
        expect(fake.events).toEqual([
            'fill', 'wait', 'click', 'rejected',
            'fill', 'wait', 'click', 'resolved',
        ]);
    } finally {
        Date.now = originalDateNow;
    }
});

test('TOTP retry rethrows a non-timeout navigation failure', async () => {
    const failure = new Error('The browser disconnected.');
    let submissions = 0;
    const fake = fakeTotpPage(() => {
        submissions++;

        throw failure;
    });

    await expect(submitTotpWithRolloverRetry(fake.page, 'JBSWY3DPEHPK3PXP', '**/new-here'))
        .rejects.toBe(failure);
    expect(submissions).toBe(1);
    expect(fake.events).toEqual(['fill', 'wait', 'click', 'rejected']);
});

test('TOTP retry rethrows a timeout when its counter has not advanced', async () => {
    const timeout = new errors.TimeoutError('TOTP navigation timed out.');
    let submissions = 0;
    const fake = fakeTotpPage(() => {
        submissions++;

        throw timeout;
    });

    await expect(submitTotpWithRolloverRetry(fake.page, 'JBSWY3DPEHPK3PXP', '**/new-here'))
        .rejects.toBe(timeout);
    expect(submissions).toBe(1);
    expect(fake.events).toEqual(['fill', 'wait', 'click', 'rejected']);
});

test('TOTP retry rethrows a timeout after a backward counter change', async () => {
    const timeout = new errors.TimeoutError('TOTP navigation timed out.');
    let currentTime = 30_000;
    let submissions = 0;
    const originalDateNow = Date.now;
    Date.now = () => currentTime;

    try {
        const fake = fakeTotpPage(() => {
            submissions++;
            currentTime = 29_999;

            throw timeout;
        });

        await expect(submitTotpWithRolloverRetry(fake.page, 'JBSWY3DPEHPK3PXP', '**/new-here'))
            .rejects.toBe(timeout);
        expect(submissions).toBe(1);
        expect(fake.events).toEqual(['fill', 'wait', 'click', 'rejected']);
    } finally {
        Date.now = originalDateNow;
    }
});

async function submitTotpWithRolloverRetry(page: Page, secret: string, destination: string): Promise<void> {
    const code = page.getByLabel('Authentication code');
    const submit = page.getByRole('button', { name: 'Continue' });

    for (let attempt = 0; attempt < 2; attempt++) {
        const counter = totpCounter();
        await code.fill(totp(secret, counter));

        const navigation = page.waitForURL(destination, { timeout: 5_000 });

        await submit.click();

        try {
            await navigation;

            return;
        } catch (error) {
            if (! (error instanceof errors.TimeoutError)
                || attempt === 1
                || totpCounter() <= counter) {
                throw error;
            }
        }
    }

    throw new Error('Unreachable TOTP retry state.');
}

function totpCounter(): number {
    return Math.floor(Date.now() / 30_000);
}

function totp(secret: string, counter = totpCounter()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';

    for (const character of secret.replace(/=+$/, '').toUpperCase()) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }

    const key = Buffer.from(bits.match(/.{1,8}/g)?.map((octet) => Number.parseInt(octet.padEnd(8, '0'), 2)) ?? []);
    const counterBuffer = Buffer.alloc(8);
    counterBuffer.writeUInt32BE(Math.floor(counter / 0x1_0000_0000), 0);
    counterBuffer.writeUInt32BE(counter >>> 0, 4);

    const digest = createHmac('sha1', key).update(counterBuffer).digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const value = ((digest[offset] & 0x7f) << 24)
        | (digest[offset + 1] << 16)
        | (digest[offset + 2] << 8)
        | digest[offset + 3];

    return String(value % 1_000_000).padStart(6, '0');
}

function fakeTotpPage(onSubmission: () => void): { page: Page; events: string[] } {
    const events: string[] = [];
    let navigation: { resolve: () => void; reject: (error: unknown) => void } | null = null;

    return {
        page: {
            getByLabel: () => ({
                fill: async () => {
                    events.push('fill');
                },
            }),
            getByRole: () => ({
                click: async () => {
                    events.push('click');

                    if (navigation === null) {
                        throw new Error('Navigation was not registered before submission.');
                    }

                    try {
                        onSubmission();
                        events.push('resolved');
                        navigation.resolve();
                    } catch (error) {
                        events.push('rejected');
                        navigation.reject(error);
                    }
                },
            }),
            waitForURL: () => {
                events.push('wait');

                return new Promise<void>((resolve, reject) => {
                    navigation = { resolve, reject };
                });
            },
        } as unknown as Page,
        events,
    };
}
