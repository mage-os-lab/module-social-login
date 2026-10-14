# Troubleshooting

Error details are logged in `var/log/system.log` with the `SocialLogin:` prefix; start there.

## No social buttons

A button is shown only if **all** of these are set for the current website:

- `digitalway_sociallogin/general/enabled` = 1
- `digitalway_sociallogin/<provider>/enabled` = 1
- client ID **and** client secret of that provider

```bash
bin/magento config:show | grep digitalway_sociallogin
```

Then flush the cache (`bin/magento cache:flush`). On the checkout, the buttons are in the
sign-in popup, not in the email step.

## "This sign-in method is not available."

The provider in the URL does not exist or is not enabled (see above). It also happens when a
custom provider's pool item name differs from its `getCode()`.

## Redirect URI mismatch at the provider

Google `redirect_uri_mismatch`, Facebook "URL Blocked", LinkedIn "The redirect_uri does not match
the registered value", Instagram "Invalid redirect_uri".

The URI sent by the module must be **identical** to one registered in the provider console. Copy it
from the read-only *Redirect URI* field in the admin, at the scope of the website you are testing.
Common differences:

- `http` instead of `https`: check `web/secure/base_url`.
- `www.` present on one side only.
- missing trailing slash.
- store code in the URL (`web/url/use_store`): each store view has its own URI.

## "Invalid or expired request. Please try signing in again."

The `state` returned by the provider does not match one stored in the session. Causes:

- more than 30 minutes between the click and the return;
- the session cookie was lost on the way back, typically because the flow started on a different
  domain (`www.` vs no `www.`, or `http` vs `https`) than the redirect URI;
- the same callback URL was opened twice (the state is single-use), e.g. with the back button.

## "Could not complete sign-in with ... Please try again."

The code exchange or the profile request failed. The log line contains the HTTP status and the
OAuth error code, for example:

| Logged error | Meaning |
|---|---|
| `invalid_client` | Wrong client ID or secret, or the secret was regenerated |
| `invalid_grant` | Code already used or expired, or the redirect URI differs from the one used in the authorization request |
| `Connection error to ...` | The server cannot reach the provider (firewall, DNS, outbound proxy) |

## Facebook: "Invalid Scopes: email"

The app does not have the email permission. In the Meta developer dashboard:

1. use an app of type **Consumer** with the **Facebook Login** product (or, in the newer
   dashboard, the use case *Authenticate and request data from users with Facebook Login*);
2. in the use case permissions, add **email** next to **public_profile**.

Both permissions are available with standard access, without App Review.

## Facebook: the account is not linked and the form asks for the email

Facebook returns the email only if the user has a confirmed email address and grants it. If the
email is missing, the customer completes the form; this is expected.

## Facebook / Instagram: works for you, not for other users

The Meta app is still in **Development** mode, where only users with a role on the app can sign
in. Switch it to **Live** (privacy policy and data deletion URLs are required). For Instagram,
users other than the app testers also require **App Review** with Advanced Access on
`instagram_business_basic`.

## Instagram: personal accounts cannot sign in

Instagram Login supports Business and Creator accounts only. This is a Meta limitation: the Basic
Display API, which supported personal accounts, was retired in December 2024.

## LinkedIn: "unauthorized_scope_error"

The app is missing the **Sign In with LinkedIn using OpenID Connect** product (Products tab). A
LinkedIn app also requires a linked Company Page.

## "Your account is not confirmed yet"

The store requires email confirmation (*Customer Configuration → Create New Account Options →
Require Emails Confirmation*) and the customer has not confirmed yet. The customer must follow the
link in the confirmation email.

## "Your account is already linked to another profile on this network."

The customer already has a different profile of the same provider linked (one profile per
provider per customer). To link a different one, delete the existing row for that customer and
provider from `digitalway_social_identity`.

## Testing locally

Facebook and Instagram accept HTTPS redirect URIs only, and every provider must be able to redirect
the browser back to the store. Use a local HTTPS domain (e.g. `https://shop.test`) registered in
the provider console, or test those providers on a staging server.
