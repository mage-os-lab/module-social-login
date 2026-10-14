# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-10-14
### Added
- **Custom button icons**: providers can ship their own icon by implementing the optional
  `Digitalway\SocialLogin\Api\ProviderIconInterface`, and any icon (bundled providers included)
  can be replaced without code through the `icons` argument of
  `Digitalway\SocialLogin\Model\ButtonsProvider` in `di.xml`. Values are view asset IDs or absolute
  URLs. Previously a provider added by another module could not have an icon.
- **Documentation** in `docs/`: configuration (paths and `bin/magento config:set` examples), how it
  works (flow, linking rules, identity table, security), adding a custom provider (complete GitLab
  example) and troubleshooting.
- A `context7.json` lets Context7 index the README and the documentation, with usage rules for the
  module.

## [0.2.2] - 2026-10-14
### Changed
- **Bounded Magento dependency ranges**: the six `magento/*` requirements now use caret constraints
  (e.g. `^103.0.4` instead of `>=103.0.4`), so a future incompatible major cannot satisfy them.
  Minimum versions are unchanged (Fixes #5, thanks to @szpGiuseppe in #6).
- **No embedded version in `composer.json`**: the package version now comes from the Git tags only.
- **CI**: the workflow now targets the current Mage-OS versions (graycore actions v8.11.0). The coding
  standard check runs with the Magento2 standard installed outside the module: errors fail the build,
  warnings are reported. The integration test job was removed until the module has an integration suite.

## [0.2.1] - 2026-10-08
### Fixed
- **Checkout popup referer padding**: accepted Magento's tilde (`~`) base64 padding in the referer allowlist so checkout destination is preserved after social sign-in (Fixes #1).
- **Welcome email password setup link**: generated password reset token (`rp_token`) prior to sending the new account welcome email, preventing expired link errors when customers set their initial password (Fixes #2).
- **Multi-website account sharing scope**: aligned identity lookups and uniqueness with Magento's account sharing configuration (`customer/account_share/scope`), allowing globally shared customers to sign in seamlessly across websites (Fixes #3).
- **Concurrent OAuth flows in the same session**: stored a bounded map of independent OAuth states in session (`MAX_STATES = 10`, 30-minute TTL) so starting a flow in a second tab does not overwrite or invalidate concurrent flows (Fixes #4).

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
