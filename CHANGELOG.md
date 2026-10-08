# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-10-08
### Changed
- The admin configuration moved from Stores → Configuration → Digitalway → Social Login to
  **Stores → Configuration → Customers → Social Login**. The configuration paths are unchanged,
  so saved settings are kept.
- Coding standards compliance: removed `final` keywords from models and test classes to comply with `Magento2.Classes.FinalImplementation`.

### Added
- Added `Magento_Backend` and `Magento_Config` to `<sequence>` in `etc/module.xml`.
- Security policy (`SECURITY.md`) and GitHub issue templates.
- GitHub Actions workflow (graycore `check-extension`): coding standard, `setup:di:compile` and
  unit tests against the supported Mage-OS versions.

## [0.1.0] - 2026-09-29
First public release.

### Added
- **Sign-in and registration with Google, Facebook, LinkedIn and Instagram** via OAuth2, with no
  external Composer dependencies. LinkedIn uses OpenID Connect; Instagram uses Instagram Login
  (Business/Creator accounts only).
- **Social buttons** on the login page, on the registration page and in the checkout sign-in popup.
- **Admin configuration** in Stores → Configuration → Digitalway → Social Login: global and
  per-provider switches, ID and Secret (stored encrypted), read-only Redirect URI to copy into the
  provider console.
- **Linking profiles to customers** in the `digitalway_social_identity` table (one profile per
  provider per customer, website-scoped):
  - profile already linked: immediate sign-in;
  - email verified by the provider and existing customer: link and sign in;
  - verified email and no customer: account created with a welcome email, then signed in.
- **"Complete sign-in" form** (email, first name, last name) when the provider does not supply a
  verified email. If the email belongs to an existing account there is no automatic linking: the
  social profile is linked only after the password sign-in to that account.
- **Security**: single-use `state` parameter against CSRF, return URL restricted to the store
  domain, session regenerated on sign-in, no codes, tokens or secrets in the logs.
- Translations: English, Italian, German, French, Spanish, Dutch, Brazilian Portuguese,
  Simplified Chinese.
- Provider-return simulator (`/sociallogin/dev/simulate`), active in developer mode only (404
  otherwise), to try the flows without real OAuth apps.
- Unit tests in `Test/Unit/`.

### Compatibility
- Magento 2.4.6+ / Mage-OS 3.x, PHP 8.1 – 8.5; tested on Mage-OS 3.5.0 with PHP 8.5.
