# Roles and Permissions

Ship fixed conceptual roles with configurable module capabilities:

- Registered User
- Verified Member
- Walk Leader
- Moderator
- Administrator

## Default intent

Registered user: profile, favourites, photo upload after email verification/policy consent.  
Verified member: mostly metadata; selected restricted resources if configured.  
Walk Leader: create walks, publish own walks when installation policy permits, edit own walks, optional own-event photo moderation/featured selection.  
Moderator: configured moderation/content modules.  
Administrator: full operational control subject to high-risk confirmation rules.

NDWG default: leaders publish own walks directly and can moderate photos for own events; global moderators/admins can moderate all.

Permissions are module-aware. Avoid arbitrary custom roles in v1.
