# Repository Layout Policy

Repository organisation is part of maintainability.

The implementation plan will scaffold Laravel and lock exact paths, but the intended direction is:

```text
app/
  Domain/
    Events/
    Gallery/
    Membership/
    Content/
    Governance/
    Operations/
  Http/
  Providers/
  Support/
bootstrap/
config/
database/
public/
resources/
  css/
  js/
  lang/
  views/
routes/
storage/
tests/
docs/
scripts/
```

## Rules

- Prefer conventional Laravel locations unless a real domain boundary improves discoverability.
- Do not create top-level folders casually.
- Avoid dumping grounds such as Misc/Common/Utils.
- Controllers coordinate requests; they do not own domain logic.
- Blade templates render data; they do not own business rules.
- Focused files are preferred to enormous all-purpose classes.
- Add a repository map to README as the structure becomes concrete.
- The release ZIP is generated from source and is not committed as source.
