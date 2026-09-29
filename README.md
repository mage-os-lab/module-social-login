# Digitalway_SocialLogin

Magento 2 / Mage-OS module to sign in or register with **Google, Facebook, LinkedIn and Instagram**.
The buttons appear on the login page, on the registration page and in the checkout sign-in popup.

- Compatibility: Magento 2.4.6+ / Mage-OS 3.x, PHP 8.1 – 8.5 (tested on Mage-OS 3.5.0, PHP 8.5)
- No external Composer dependencies
- Languages: English, Italian, German, French, Spanish, Dutch, Brazilian Portuguese, Simplified Chinese

## Installation

**With Composer**:
```bash
composer config repositories.social-login vcs https://github.com/mage-os-lab/module-social-login
composer require digitalway/module-social-login
bin/magento setup:upgrade
```

**In `app/code`**: copy the module files into `app/code/Digitalway/SocialLogin/`, then run `bin/magento setup:upgrade`.


## Configuration

**Stores → Configuration → Digitalway → Social Login**: enable the module, then set Enabled, ID and Secret
for each provider. Copy the read-only **Redirect URI** field into the provider console:
`https://<domain>/sociallogin/account/callback/provider/<google|facebook|linkedin|instagram>/`.

### Google
1. Google Cloud Console → APIs & Services → OAuth consent screen: user type "External", then publish it to production.
2. Credentials → Create credentials → OAuth client ID → "Web application".
3. "Authorized redirect URIs": the Google Redirect URI.

### Facebook
1. developers.facebook.com → Create app → type "Consumer" → add the **Facebook Login** product.
2. Facebook Login → Settings → "Valid OAuth Redirect URIs": the Facebook Redirect URI.
3. App settings → Basic: privacy policy URL and data deletion instructions URL (required to go Live).
4. Permissions used: `email`, `public_profile` (standard access, no App Review).

### LinkedIn
1. linkedin.com/developers → Create app (a linked Company Page is required).
2. Products → add **Sign In with LinkedIn using OpenID Connect**.
3. Auth → "Authorized redirect URLs for your app": the LinkedIn Redirect URI.

### Instagram
1. In the same Meta app (or a new one) add the **Instagram** product → "API setup with Instagram login".
2. "Business login settings" → "OAuth redirect URIs": the Instagram Redirect URI (HTTPS required).
3. Users other than the app testers require **App Review** with Advanced Access on `instagram_business_basic`.

## Behaviour

| Situation | Result |
|---|---|
| Social profile already linked | Immediate sign-in |
| Email verified by the provider, existing customer | Link and sign in |
| Verified email, no customer | Account created (welcome email) and signed in |
| Missing (Instagram, Facebook without email) or unverified email | "Complete sign-in" form: email, first name, last name |
| In the form, email of an existing account | No automatic linking: the customer signs in with their password and only then is the social profile linked |

Identities are stored in the `digitalway_social_identity` table (one per provider per customer, website-scoped).

## Known limitations

- **Instagram**: Business/Creator accounts only, and no email (a Meta limitation since the Basic Display API was retired in December 2024).
- **Facebook**: the email is missing if the user has no confirmed email or does not grant it.
- **Facebook and Instagram** accept HTTPS Redirect URIs only: they cannot be tried locally with real apps.

## Tests

Unit tests live in `Test/Unit/` and use the Magento test framework. Run them from the root of the
Magento / Mage-OS installation where the module is installed:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/digitalway/module-social-login/Test/Unit
```

If the module is in `app/code`, the path is `app/code/Digitalway/SocialLogin/Test/Unit`.

## License

[MIT](LICENSE)

