# Adding a custom provider

Providers are plain classes registered in a pool through `di.xml`. You can add one from your own
module without touching `Digitalway_SocialLogin`. This guide adds **GitLab** (OpenID Connect) in a
module called `Vendor_GitLabLogin`.

A provider:

- implements `Digitalway\SocialLogin\Api\ProviderInterface`, usually by extending
  `Digitalway\SocialLogin\Model\Provider\AbstractProvider`;
- builds the authorization URL, exchanges the code for a token and reads the user profile;
- returns a normalized `Digitalway\SocialLogin\Model\Profile`.

Everything else (state/CSRF protection, return URL, customer linking and creation, the email
completion form, sign-in) is handled by the module.

## 1. Module declaration

`app/code/Vendor/GitLabLogin/etc/module.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_GitLabLogin">
        <sequence>
            <module name="Digitalway_SocialLogin"/>
        </sequence>
    </module>
</config>
```

## 2. The provider class

`app/code/Vendor/GitLabLogin/Model/Provider/GitLab.php`:

```php
<?php
declare(strict_types=1);

namespace Vendor\GitLabLogin\Model\Provider;

use Digitalway\SocialLogin\Api\ProviderIconInterface;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\Provider\AbstractProvider;

class GitLab extends AbstractProvider implements ProviderIconInterface
{
    public const CODE = 'gitlab';

    private const AUTH_URL = 'https://gitlab.com/oauth/authorize';
    private const TOKEN_URL = 'https://gitlab.com/oauth/token';
    private const USERINFO_URL = 'https://gitlab.com/oauth/userinfo';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'GitLab';
    }

    public function getIcon(): string
    {
        return 'Vendor_GitLabLogin::images/gitlab.svg';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return $this->buildUrl(self::AUTH_URL, [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
        ]);
    }

    protected function requestProfile(string $code): array
    {
        $token = $this->requireAccessToken($this->http->postForm(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
        ]));

        return $this->http->get(self::USERINFO_URL, [], $token);
    }

    public function normalizeProfile(array $raw): Profile
    {
        $id = $this->requireId($raw, 'sub');
        $email = $this->emailValue($raw);
        [$first, $last] = $this->splitName($this->stringValue($raw, 'name'));

        return new Profile(
            self::CODE,
            $id,
            $email,
            $email !== null && $this->isTrue($raw['email_verified'] ?? false),
            $first,
            $last
        );
    }
}
```

What the pieces do:

| Member | Purpose |
|---|---|
| `getCode()` | Stable code used in URLs, configuration paths and the identity table. Lowercase, max 32 characters. Never change it once customers are linked. |
| `getLabel()` | Shown on the button ("Continue with GitLab") and in messages. |
| `getAuthorizationUrl($state)` | URL the customer is sent to. Always pass `$state` through unchanged. |
| `requestProfile($code)` | Exchanges the authorization code for a token and returns the raw profile array. Throw `HttpException` (the `HttpClient` already does) on network or HTTP errors. |
| `normalizeProfile($raw)` | Pure conversion from the raw array to a `Profile`; no network calls, easy to unit test. |
| `getIcon()` | Optional, from `ProviderIconInterface`: asset ID or absolute URL of the button icon. |

Helpers inherited from `AbstractProvider`: `clientId()`, `clientSecret()` (decrypted),
`redirectUri()`, `buildUrl()`, `requireAccessToken()`, `requireId()`, `stringValue()`,
`emailValue()`, `isTrue()`, `splitName()`. The injected `$this->http`
(`Digitalway\SocialLogin\Model\Http\HttpClient`) offers `postForm()` and `get()` with JSON
decoding, timeouts and TLS verification.

### The Profile

```php
new Profile(
    string $provider,        // the provider code
    string $providerUserId,  // stable user ID at the provider (not the email)
    ?string $email,          // null if the provider does not return one
    bool $emailVerified,     // true ONLY if the provider guarantees the email is verified
    string $firstname,
    string $lastname
);
```

`$emailVerified` decides whether the profile can be linked to an existing customer with the same
email, so set it to `true` only when the provider states that the address is verified. If the
email is missing or unverified, or a name is missing for a new customer, the customer is asked to
complete their details instead (see [How it works](how-it-works.md)).

## 3. Register the provider

`app/code/Vendor/GitLabLogin/etc/di.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="Digitalway\SocialLogin\Model\ProviderPool">
        <arguments>
            <argument name="providers" xsi:type="array">
                <item name="gitlab" xsi:type="object">Vendor\GitLabLogin\Model\Provider\GitLab</item>
            </argument>
        </arguments>
    </type>
</config>
```

The item name must be equal to `getCode()`. Buttons are shown in the order of the pool items.

## 4. Admin configuration

The module reads `digitalway_sociallogin/<code>/enabled`, `client_id` and `client_secret`. Add a
group with the same code to the existing section.

`app/code/Vendor/GitLabLogin/etc/adminhtml/system.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">
    <system>
        <section id="digitalway_sociallogin">
            <group id="gitlab" translate="label" sortOrder="60" showInDefault="1" showInWebsite="1" showInStore="0">
                <label>GitLab</label>
                <field id="enabled" translate="label" type="select" sortOrder="10" showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Enabled</label>
                    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
                </field>
                <field id="client_id" translate="label" type="text" sortOrder="20" showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Application ID</label>
                </field>
                <field id="client_secret" translate="label" type="obscure" sortOrder="30" showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Secret</label>
                    <backend_model>Magento\Config\Model\Config\Backend\Encrypted</backend_model>
                </field>
                <field id="redirect_uri" translate="label" type="text" sortOrder="40" showInDefault="1" showInWebsite="1" showInStore="0">
                    <label>Redirect URI</label>
                    <frontend_model>Digitalway\SocialLogin\Block\Adminhtml\System\Config\RedirectUri</frontend_model>
                </field>
            </group>
        </section>
    </system>
</config>
```

The `client_secret` field **must** use the `Encrypted` backend model: the module always decrypts
it. The `RedirectUri` frontend model shows the callback URL to copy into the provider console
(`https://<domain>/sociallogin/account/callback/provider/gitlab/`).

## 5. Icon

Put the icon in `app/code/Vendor/GitLabLogin/view/frontend/web/images/gitlab.svg` and return its
asset ID from `getIcon()`, as in the class above. Without `ProviderIconInterface` (or with an
empty value) the module looks for `Digitalway_SocialLogin::images/<code>.svg`, which does not exist
for custom providers. The icon can also be set or overridden in `di.xml`, see
[Configuration → Button icons](configuration.md#button-icons).

## 6. Enable and test

```bash
bin/magento module:enable Vendor_GitLabLogin
bin/magento setup:upgrade
bin/magento config:set digitalway_sociallogin/gitlab/enabled 1
bin/magento config:set digitalway_sociallogin/gitlab/client_id "..."
bin/magento config:set digitalway_sociallogin/gitlab/client_secret "..."
bin/magento cache:flush
```

`normalizeProfile()` has no dependencies on the network, so it is easy to cover with unit tests:
pass a sample raw response and assert on the returned `Profile`.

## Replacing a bundled provider

To change the behaviour of a bundled provider (e.g. extra scopes), extend its class and replace
the pool item with the same name:

```xml
<type name="Digitalway\SocialLogin\Model\ProviderPool">
    <arguments>
        <argument name="providers" xsi:type="array">
            <item name="google" xsi:type="object">Vendor\Module\Model\Provider\Google</item>
        </argument>
    </arguments>
</type>
```
