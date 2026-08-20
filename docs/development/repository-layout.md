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
- Product-domain code belongs under `App\Domain\<Domain>` using one of the approved roots: `Events`, `Gallery`, `Membership`, `Content`, `Governance`, or `Operations`.
- HTTP controllers, requests, and middleware remain under `App\Http` and coordinate delivery rather than owning domain rules.
- Narrowly defined cross-cutting application code may use `App\Support`; it is not a general-purpose dumping ground.
- Do not create top-level folders casually.
- Dumping-ground roots such as `Helpers`, `Utils`, `Misc`, and `Common` are prohibited and guarded by an architecture test.
- Controllers coordinate requests; they do not own domain logic.
- Blade templates render data; they do not own business rules.
- Focused files are preferred to enormous all-purpose classes.
- Add a repository map to README as the structure becomes concrete.
- The release ZIP is generated from source and is not committed as source.

## Placement examples

- An action that publishes or reschedules an event belongs with the Events domain, for example `App\Domain\Events\Actions\PublishEvent`.
- A policy that governs community-photo moderation belongs with the Gallery domain, for example `App\Domain\Gallery\Policies\PhotoPolicy`.
- A service that performs backup integrity checks belongs with Operations, for example `App\Domain\Operations\Services\VerifyBackup`.
