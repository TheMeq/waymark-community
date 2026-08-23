# Public performance budgets

Waymark is server-rendered and must remain usable on ordinary shared hosting and mobile connections. The developer/release audit runs Lighthouse with deterministic local transport and applies these regression ceilings to the homepage and gallery:

| Measure | Automated ceiling |
| --- | ---: |
| Public CSS transfer | 100 KiB |
| Public JavaScript transfer | 260 KiB |
| Initial image transfer | 1 MiB |
| Total initial transfer | 1.5 MiB |
| Lighthouse performance score | at least 0.65 |
| Largest Contentful Paint | 4,000 ms |
| Total Blocking Time | 500 ms |
| Cumulative Layout Shift | 0.10 |

The release target under representative production hosting and a mobile profile is LCP at or below 2.5 seconds, INP at or below 200 ms, and CLS at or below 0.10 at the 75th percentile. Local CI thresholds are intentionally less strict for timing metrics because runner speed is not production field data; resource ceilings and layout shift remain hard regression guards.

Run `npm run test:performance` against a prepared local browser-test server. Images below the fold must use native lazy loading, image dimensions must be supplied where known, and generated media should use the existing responsive variants. The default photographic fixtures include 768px and 1536px WebP sources; the approved concept artwork is not changed or served as a runtime asset.

File or database cache is the supported default. Redis can be selected through Laravel's normal `CACHE_STORE` configuration but is optional and must never become a production requirement.
