# Release browser matrix

The ordinary Playwright gate preserves the approved desktop, tablet, and mobile visual baselines across critical public views. It also covers WCAG A/AA axe checks, 200% text resize and overflow, keyboard navigation, gallery interactions, and PWA install/offline behaviour.

The release-critical gate adds engine coverage:

```sh
npx playwright install chromium firefox webkit
npm run test:e2e:release
```

It runs the homepage, Walks, What's on, and Gallery through current Playwright Chromium, Firefox, desktop WebKit, and mobile WebKit. Every page must return successfully, pass the WCAG 2.2 A/AA automated semantic rules, and remain free of horizontal overflow at 200% text size. The cross-engine sweep disables axe's geometry-sensitive `target-size` rule because the full fixed-viewport visual/accessibility suite already checks that rule without engine font-metric noise. The gate also checks keyboard skip navigation and live manifest, service-worker, and offline endpoints.

Playwright supplies current browser engines; CI cannot pin genuine previous installed majors for every vendor. Before a v1 release, manually smoke the current and previous major versions of Chrome, Edge, Firefox, and Safari where those vendor builds are available, including mainstream iOS Safari and Android Chrome. Record browser versions, page set, keyboard navigation, responsive layout, installation/offline result, and any accepted limitation in the release evidence. Automated WebKit is a useful Safari signal, not a claim that Safari itself was exercised.

Playwright WebKit follows Safari's platform setting that can omit links from sequential Tab focus. The automated WebKit projects therefore focus the skip link before sending the keyboard activation key; the Chromium and Firefox projects assert that it is the first Tab stop. Manual Safari checks must enable full keyboard access and verify the complete sequential order.
