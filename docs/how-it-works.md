# How it works

## Sign-in flow

```
Button  ──►  /sociallogin/account/redirect/provider/<code>/[referer/<encoded>]
                │  creates a single-use OAuth state bound to the provider and the return URL
                ▼
Provider consent screen
                │
                ▼
/sociallogin/account/callback/provider/<code>/?code=...&state=...
                │  validates and consumes the state, exchanges the code, reads the profile
                ▼
Customer resolution (see below)  ──►  signed in and redirected to the return URL
                                 └─►  or "Complete sign-in" form
```

1. **Redirect** (`Controller\Account\Redirect`): a customer who is already signed in goes to
   *My Account*. Otherwise the module checks that the provider is enabled, validates the optional
   `referer` and stores a random `state` in the customer session.
2. **Callback** (`Controller\Account\Callback`): if the customer cancelled at the provider, a
   notice is shown. Otherwise the state is consumed, the code is exchanged for a token and the
   profile is normalized into a `Profile`.
3. **Resolution** (`Model\CustomerLinker`) decides whether to sign in, link, create or ask for
   more data.
4. **Sign-in** (`Model\LoginHandler`): accounts that still require email confirmation are refused
   with a message; otherwise the customer is signed in (the session ID is regenerated) and the
   private content cache cookie is reset, so the header shows the customer immediately.

## Customer resolution

| Situation | Result |
|---|---|
| The social profile is already linked | Sign in that customer |
| Verified email, a customer with that email exists | Link the profile and sign in |
| Verified email, no customer, first and last name known | Create the customer, link and sign in |
| Email missing or not verified, or names missing for a new customer | "Complete sign-in" form |

The rule that matters for security: **a profile is never linked to an existing account on an
email that the provider has not verified.** Only Google and LinkedIn report verification
explicitly; Facebook returns only confirmed emails; Instagram returns no email at all.

### Complete sign-in form

`/sociallogin/account/completeemail` asks for email, first name and last name. The pending profile
is kept in the session for 30 minutes.

- **New email**: the customer is created with Magento's standard registration flow, so the
  *Require Emails Confirmation* setting is honoured: if it is on, the customer must confirm the
  email before signing in.
- **Email of an existing account**: no automatic linking. The customer is asked to sign in with
  their password; after a successful sign-in with that email (`customer_data_object_login` event)
  the social profile is linked to the account.

### Accounts created from a verified email

They get a random password and the standard welcome email with a *set your password* link, so the
customer can later sign in with email and password as well.

## Identity table

Links are stored in `digitalway_social_identity`:

| Column | Description |
|---|---|
| `identity_id` | Primary key |
| `customer_id` | Customer (deleted together with the customer) |
| `website_id` | Website where the link was created |
| `provider` | Provider code (`google`, `facebook`, ...) |
| `provider_user_id` | User ID at the provider |
| `email` | Email at link time (informational) |
| `created_at` | Link date |

Constraints: a customer has at most one profile per provider, and a provider profile is linked to
at most one customer per website.

### Account sharing scope

The module follows **Stores → Configuration → Customers → Customer Configuration → Account Sharing
Options**:

- **Per Website**: identities are looked up and checked per website, like customers.
- **Global**: identities are looked up across all websites, so a customer linked on one website
  signs in with the same social profile on every website.

## Security

- **State / CSRF**: each flow has a random, single-use state, bound to the provider and valid for
  30 minutes. Up to 10 flows can be pending in the same session, so sign-ins started in several
  tabs do not invalidate each other.
- **Open redirects**: the return URL (`referer`) is accepted only if it is an absolute `http(s)`
  URL of one of the store's own domains; anything else falls back to *My Account*.
- **Secrets**: client secrets are stored encrypted and never logged. HTTP errors from the
  providers are logged with the OAuth error code only, never with the response body or tokens.
- **TLS**: provider calls verify certificates and do not follow redirects.

## Where the buttons appear

- Customer login page and registration page.
- Checkout sign-in popup (the customer returns to the checkout after signing in).

The buttons come from `Digitalway\SocialLogin\Model\ButtonsProvider`, which lists the enabled
providers of `Digitalway\SocialLogin\Model\ProviderPool`.

## Logging

Failures are written to the standard Magento log (`var/log/system.log`) with the `SocialLogin:`
prefix, e.g. `SocialLogin: sign-in failed` with the provider code and the
error. Customers only see generic messages.
