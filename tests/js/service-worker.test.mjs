import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = (await readFile(new URL('../../resources/views/pwa/service-worker.blade.php', import.meta.url), 'utf8'))
    .replace('{{ $cacheVersion }}', 'waymark-shell-test');

function workerHarness({ cached = new Map(), keys = [], fetchImplementation = async () => ({ ok: true, clone() { return this; } }) } = {}) {
    const listeners = new Map();
    const deleted = [];
    const put = [];
    let claimed = false;
    let fetches = 0;
    const cache = {
        addAll: async () => {},
        put: async (request, response) => { put.push([request, response]); },
    };
    const caches = {
        keys: async () => keys,
        delete: async (key) => { deleted.push(key); return true; },
        open: async () => cache,
        match: async (request) => cached.get(typeof request === 'string' ? request : new URL(request.url).pathname),
    };
    const self = {
        location: { origin: 'https://waymark.test' },
        clients: { claim: () => { claimed = true; } },
        skipWaiting: () => {},
        addEventListener: (type, listener) => listeners.set(type, listener),
    };
    const fetch = async (request) => { fetches++; return fetchImplementation(request); };

    vm.runInNewContext(source, { self, caches, fetch, URL, Promise });

    return {
        deleted,
        get claimed() { return claimed; },
        get fetches() { return fetches; },
        async activate() {
            let completion;
            listeners.get('activate')({ waitUntil: (promise) => { completion = promise; } });
            await completion;
        },
        async fetch(request) {
            let response;
            listeners.get('fetch')({ request, respondWith: (promise) => { response = Promise.resolve(promise); } });
            return response ? response : undefined;
        },
    };
}

function request(path, { method = 'GET', mode = 'navigate' } = {}) {
    return { url: `https://waymark.test${path}`, method, mode };
}

test('activation deletes obsolete Waymark caches but retains current and foreign caches', async () => {
    const harness = workerHarness({ keys: ['waymark-shell-v1', 'waymark-shell-test', 'another-app-cache'] });
    await harness.activate();

    assert.deepEqual(harness.deleted, ['waymark-shell-v1']);
    assert.equal(harness.claimed, true);
});

test('explicit compiled assets are cache first', async () => {
    const cachedResponse = { source: 'cache' };
    const harness = workerHarness({ cached: new Map([['/build/assets/app-hash.js', cachedResponse]]) });

    assert.equal(await harness.fetch(request('/build/assets/app-hash.js', { mode: 'no-cors' })), cachedResponse);
    assert.equal(harness.fetches, 0);
});

test('offline fallback is limited to known public read navigation paths', async () => {
    const offline = { source: 'offline' };
    const harness = workerHarness({
        cached: new Map([['/offline', offline]]),
        fetchImplementation: async () => { throw new Error('offline'); },
    });

    for (const path of ['/', '/walks', '/walks/a-safe-slug', '/photos', '/photos/42', '/photos/events/a-safe-slug', '/socials/coffee', '/weekends/coast', '/whats-on/calendar', '/leaders/alex']) {
        assert.equal(await harness.fetch(request(path)), offline, path);
    }
});

test('mutations, private and unknown navigations bypass the service worker', async () => {
    const harness = workerHarness();
    const bypassed = [
        request('/walks', { method: 'POST' }),
        request('/login'),
        request('/register'),
        request('/forgot-password'),
        request('/email/verify/1/token'),
        request('/user/confirm-password'),
        request('/user/two-factor-authentication'),
        request('/admin'),
        request('/account/profile'),
        request('/leader-hub'),
        request('/photos/upload'),
        request('/media/1/image/large'),
        request('/photos/1/image/large'),
        request('/photos/1/download?signature=signed'),
        request('/walks/public-looking?signature=signed'),
        request('/account/privacy/exports/1?signature=signed'),
        request('/api/private'),
        request('/unknown-public-looking-path'),
    ];

    for (const candidate of bypassed) {
        assert.equal(await harness.fetch(candidate), undefined, candidate.url);
    }
    assert.equal(harness.fetches, 0);
});
