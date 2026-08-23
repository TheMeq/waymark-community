# Accessibility acceptance checklist

Waymark targets WCAG 2.2 AA where applicable. Run the automated Playwright and axe suite first, then complete this checklist at desktop, tablet, and mobile widths before a release. Record the browser, assistive technology where used, tester, date, and any issue reference.

## Keyboard and focus

- Navigate the header, mobile menu, search filters, cookie settings, authentication, public forms, calendar, gallery lightbox, and representative admin forms using only the keyboard.
- Confirm focus order follows reading order and every interactive control has a visible focus indicator.
- Activate menus, disclosures, dialogs, filters, form submission, lightbox previous/next, and close controls with their expected keyboard keys.
- Confirm modal and lightbox focus moves inside when opened, remains trapped while open, returns to the triggering control when closed, and can be closed with Escape.

## Zoom, reflow, motion, and touch

- At 200% browser text size, confirm content reflows without horizontal page scrolling, clipping, hidden controls, or overlapping fixed content.
- With reduced motion enabled at operating-system level, confirm decorative animation and smooth scrolling are suppressed and no information depends on motion.
- At mobile width, confirm primary controls and isolated icon controls have at least a 44 by 44 CSS-pixel target or equivalent spacing.
- Check portrait and landscape mobile layouts and that browser zoom remains enabled.

## Meaning and feedback

- Confirm headings and landmarks describe the page structure, images have appropriate alternatives, and decorative images are ignored.
- Confirm status, availability, errors, moderation state, and required fields are not communicated by colour alone.
- Submit each representative form with missing and invalid values; confirm errors are announced, associated with fields, understandable, and preserve entered values.
- Check live status messages such as saved preferences and upload/moderation outcomes with a screen reader.

## Colour and branding

- Exercise the branding editor with the lightest and darkest permitted colours and confirm its contrast warning appears for combinations below the target.
- Confirm generated foreground colours remain readable and run axe against the branding previews.
- Manually inspect text over photography and focus indicators across surface, brand, accent, error, and disabled states.

Automated checks support this review but do not replace it. File failures against the affected journey and do not waive WCAG A/AA failures without an explicit acceptance decision.
