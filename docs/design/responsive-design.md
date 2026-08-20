# Responsive Design

The mobile site is not “desktop stacked smaller”. It may reorder homepage sections based on likely mobile utility while retaining the same content/design system.

High-priority mobile flows:

- browse upcoming walks;
- switch list/calendar;
- inspect walk logistics;
- install/use PWA shell;
- take/select/upload photos to current/recent event;
- gallery/lightbox;
- Join/New Here;
- account/profile/favourites.

Mobile navigation uses accessible hamburger plus prominent key actions. Avoid native-app-style bottom tabs in v1.

## Phase 2 responsive decisions

- Desktop (`1440 × 900`) keeps the reference composition: compact navigation,
  copy over the left of a panoramic hero, three walk cards beside one trip
  spotlight, a single photo band, then the joined New Here/resources area.
- Tablet (`834 × 1112`) uses native mobile navigation, preserves three compact
  walk cards, gives the trip spotlight a horizontal image/content treatment,
  and turns the photo band into a three-column gallery.
- Mobile (`390 × 844`) prioritises the hero actions, uses a swipeable/snap-aligned
  walk-card rail, preserves the trip spotlight as a large photographic card,
  and uses an asymmetric two-column gallery before the stacked join/resources
  area. DOM and keyboard order remain unchanged by these visual treatments.

Full-page PNG baselines live in `tests/visual/baselines/`. Playwright allows at
most a 1.5% differing-pixel ratio to absorb small cross-platform font raster
differences; intentional layout, token or image changes require explicit
baseline review and regeneration. The suite also checks axe WCAG A/AA rules,
keyboard operation of the mobile menu, versioned banner dismissal, and
document overflow after simulated 200% text sizing.
