# Reference Deployment Content-entry Checklist

This checklist supports the first NDWG staging deployment. Installation-specific content stays in the deployment and is never added to generic Waymark core.

The reference site is a **manual clean rebuild**. Do not scrape the legacy website or bulk-copy its markup. Use the guided Walk CSV import only where the source data has been reviewed, mapped and dry-run successfully.

## Before entry

- Confirm ownership or permission for every photograph, logo, document and passage of copy.
- Prepare a staging-only database, media directory, secrets, mail trap and administrator accounts.
- Set `APP_ENV=staging` or `WAYMARK_STAGING=true`; confirm the STAGING admin banner, `noindex,nofollow`, blocked sitemap, full robots exclusion, trapped email and absent public analytics.
- Record the approved production analytics identifier separately; do not enable it on staging.
- Inventory current content by purpose, owner, review date and destination without copying private member data.

## Configure and enter

- Enter group name, public contact routes, colours and social links through configuration/admin fields.
- In **Branding**, prefer a local raster logo upload. Preview and save it, then
  check the public header and footer. Transparent PNG logos remain PNG. Record a
  secure external URL or root-relative path only when an external fallback is
  deliberately required; replacement/removal must leave the intended source
  active.
- Upload one square raster favicon. It must be at least 32 by 32 pixels; roughly
  512 by 512 is recommended. Preview the saved browser favicon and confirm the
  managed response is PNG. Do not supply SVG or ICO. PWA install icons are
  configured separately and are not changed by Branding.
- Recreate navigation and footer links using concise approved labels.
- Enter grading definitions, existing leaders and public profile choices before importing Walks.
- Use preview and dry-run for each CSV; resolve invalid mappings, duplicates and missing referenced leaders/grades before importing private drafts.
- Recreate published Walks, Socials, Holidays, policies, governance documents, committee information, news and CMS pages manually.
- For each Walk needing a featured image, prefer one local upload and provide a
  meaningful image description for people who cannot see it. Preview the image,
  checkpoint Step 4, leave and resume the Draft, and confirm the same saved
  image and description return. Use an HTTPS URL or safe root-relative path only
  after explicitly choosing the external option. When replacing or removing a
  managed image, confirm whether a retained external fallback should become
  active or all Walk-specific imagery should be cleared.
- Upload only authorised media; add useful alt text, captions, attribution and event/album relationships.
- Accepted managed-image inputs are JPEG, PNG, WebP and AVIF when supported by
  the host codecs and configured byte, dimension, pixel and processing-memory
  limits. SVG, GIF and ICO are not supported managed uploads.
- Check dates, locations, distances, ascent, grades, leaders, optional capacity/status and download attachments.

## Visual and functional review

- Compare desktop, tablet and mobile staging captures with `docs/design/references/approved-homepage-concept.png`.
- Check the approved composition, photography, hierarchy, whitespace, card rhythm, navigation, gallery, CTAs and footer before judging copy detail.
- Review Home, Walks, What's on, Gallery, Search, News, Documents, Join/contact and account journeys with representative real-style content.
- Complete keyboard, 200% text resize, screen-reader landmarks, focus visibility, reduced-motion and contrast checks.
- Exercise contact delivery against the trap only. Confirm no staging message can reach a real public recipient.
- Confirm public analytics requests remain absent even after granting optional consent.

## Production cutover

- Obtain content-owner sign-off and record any deliberate differences from the approved concept.
- Take and verify a staging backup, then rehearse restore/recovery and the supported update path. Confirm the restored database references and private `SiteMedia` variants return together, and that the Walk image, logo and PNG favicon resolve through public application routes without exposing private paths.
- Prepare new production secrets, database, storage, mail and analytics configuration; never copy staging secrets blindly.
- Remove staging mode only after DNS/TLS, email recipients, indexing, sitemap and analytics have been independently checked.
- Run the production smoke checklist and retain the release ZIP, checksum, version metadata and deployment record.
