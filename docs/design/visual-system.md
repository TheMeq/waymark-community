# Public Visual System

## Source of truth

Primary reference: `references/approved-homepage-concept.png`.

Functional behaviour comes from the written spec. Visual direction comes from the approved concept. Do not replace it with framework defaults.

## Character

- warm, modern outdoor/community aesthetic;
- light neutral/off-white surfaces;
- charcoal text;
- restrained green brand system plus controlled accent;
- large photography and real people;
- confident headings and short copy;
- generous whitespace;
- rounded cards;
- subtle borders/shadows;
- occasional contour/topographic decoration;
- precise metadata rows for distance/ascent/difficulty/date;
- clear CTA hierarchy.

## Anti-patterns

- generic Bootstrap cards/navbars;
- Filament styling on public pages;
- giant walls of introductory copy;
- excessive gradients/glassmorphism;
- green applied to every surface simply because it is an outdoor site;
- cards with every possible data point equally prominent;
- decorative animation that ignores reduced-motion preference;
- homepage sections describing what their controls already communicate.

## Implemented design tokens

The public frontend consumes the following stable CSS properties from
`resources/css/app.css`. Installation colours may override only the four brand
properties through `BrandTheme`; all values are validated as six-digit hex
colours and paired with the higher-contrast approved foreground.

| Group | Tokens |
| --- | --- |
| Brand | `--wm-brand`, `--wm-on-brand`, `--wm-accent`, `--wm-on-accent` |
| Surfaces | `--wm-surface`, `--wm-surface-raised`, `--wm-surface-soft`, `--wm-surface-strong` |
| Text | `--wm-text`, `--wm-text-muted`, `--wm-text-inverse` |
| State | `--wm-positive`, `--wm-warning`, `--wm-critical` |
| Borders | `--wm-border`, `--wm-border-strong` |
| Spacing | `--wm-space-1` through `--wm-space-9` |
| Shape | `--wm-radius-sm`, `--wm-radius-md`, `--wm-radius-lg`, `--wm-radius-pill` |
| Elevation | `--wm-shadow-card`, `--wm-shadow-float` |
| Layout | `--wm-container`, `--wm-container-copy` |
| Interaction | `--wm-focus-ring`, `--wm-transition-fast`, `--wm-transition-base` |

The default brand is moss (`#526B3F`) with an oat accent (`#D6B269`). The
remaining palette is deliberately warm and neutral so an installation brand
does not overwhelm photography or turn every public surface green.

Instrument Sans is the single public type family in Phase 2. Display headings
use weight 600, tight tracking and a compact line height; body copy uses the
regular face at a relaxed line height. Type sizes remain fluid at component
level rather than forming a framework-like fixed scale.

The public container is capped at 80rem, with 1rem mobile and 2rem tablet-plus
gutters. Component breakpoints follow content pressure: the primary tablet
change begins at 48rem and desktop navigation/layout changes begin at 64rem.
Photography uses deliberate per-component aspect ratios rather than a global
crop. Motion is brief and functional, and the reduced-motion media query
removes animation and smooth scrolling.

## Visual implementation gate

A static, fixture-driven homepage at desktop/tablet/mobile is built before dynamic module wiring. It must be reviewed against the reference image. Only then does the homepage connect to live data.
