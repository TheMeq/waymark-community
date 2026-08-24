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

- Enter group name, public contact routes, logo/favicon, colours and social links through configuration/admin fields.
- Recreate navigation and footer links using concise approved labels.
- Enter grading definitions, existing leaders and public profile choices before importing Walks.
- Use preview and dry-run for each CSV; resolve invalid mappings, duplicates and missing referenced leaders/grades before importing private drafts.
- Recreate published Walks, Socials, Holidays, policies, governance documents, committee information, news and CMS pages manually.
- Upload only authorised media; add useful alt text, captions, attribution and event/album relationships.
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
- Take and verify a staging backup, then rehearse restore/recovery and the supported update path.
- Prepare new production secrets, database, storage, mail and analytics configuration; never copy staging secrets blindly.
- Remove staging mode only after DNS/TLS, email recipients, indexing, sitemap and analytics have been independently checked.
- Run the production smoke checklist and retain the release ZIP, checksum, version metadata and deployment record.
