import type { Page } from '@playwright/test';

const imageLoadTimeoutMs = 5_000;

export async function waitForPageImages(page: Page): Promise<void> {
    await page.locator('img').evaluateAll(async (images: HTMLImageElement[], timeoutMs: number) => {
        for (const image of images) {
            image.scrollIntoView({ block: 'center' });

            await new Promise<void>((resolve) => {
                window.requestAnimationFrame(() => resolve());
            });

            if (image.complete) {
                continue;
            }

            await new Promise<void>((resolve) => {
                const timeout = window.setTimeout(resolve, timeoutMs);
                const finish = () => {
                    window.clearTimeout(timeout);
                    resolve();
                };

                image.addEventListener('load', finish, { once: true });
                image.addEventListener('error', finish, { once: true });
            });
        }

        window.scrollTo(0, 0);
    }, imageLoadTimeoutMs);
}
