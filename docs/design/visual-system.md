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

## Required design-token groups

- semantic colours;
- spacing;
- radii;
- shadows;
- typography scale/weights/line heights;
- container widths;
- breakpoints;
- transitions/motion;
- focus ring;
- image aspect-ratio conventions.

Exact numeric token values will be extracted/approved during the first visual implementation milestone.

## Visual implementation gate

A static, fixture-driven homepage at desktop/tablet/mobile is built before dynamic module wiring. It must be reviewed against the reference image. Only then does the homepage connect to live data.
