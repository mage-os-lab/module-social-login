# Configuration

All settings are in **Stores → Configuration → Customers → Social Login**, at default or website
scope. They can also be set from the command line with `bin/magento config:set`.

## Configuration paths

| Path | Description |
|---|---|
| `digitalway_sociallogin/general/enabled` | Master switch for the module (default `0`) |
| `digitalway_sociallogin/<provider>/enabled` | Provider switch (default `0`) |
| `digitalway_sociallogin/<provider>/client_id` | Client / App ID from the provider console |
| `digitalway_sociallogin/<provider>/client_secret` | Client / App secret, stored encrypted |
| `digitalway_sociallogin/facebook/graph_version` | Graph API version for Facebook (default `v24.0`) |
| `digitalway_sociallogin/instagram/graph_version` | Graph API version for Instagram (default `v24.0`) |

`<provider>` is one of `google`, `facebook`, `linkedin`, `instagram`, or the code of a
[custom provider](custom-provider.md).

A provider button is shown only when **all** of these are true: the module is enabled, the provider
is enabled, and both the client ID and the client secret are set.

## Command line

Enable the module and Google at default scope:

```bash
bin/magento config:set digitalway_sociallogin/general/enabled 1
bin/magento config:set digitalway_sociallogin/google/enabled 1
bin/magento config:set digitalway_sociallogin/google/client_id "1234567890-abc.apps.googleusercontent.com"
bin/magento config:set digitalway_sociallogin/google/client_secret "GOCSPX-..."
bin/magento cache:flush
```

The `client_secret` field uses Magento's `Encrypted` backend model, so `config:set` stores it
encrypted, exactly like the admin form. Do not write it directly into `core_config_data`: the module
decrypts the value before using it.

Different credentials per website (for example a separate Facebook app per domain):

```bash
bin/magento config:set --scope=websites --scope-code=base digitalway_sociallogin/facebook/client_id "111111111111111"
bin/magento config:set --scope=websites --scope-code=base digitalway_sociallogin/facebook/client_secret "..."
```

Pin a different Graph API version for Facebook:

```bash
bin/magento config:set digitalway_sociallogin/facebook/graph_version v24.0
```

To keep the values out of the database (e.g. per environment), use `--lock-env` to write them to
`app/etc/env.php` instead.

## Redirect URI

Each provider needs the redirect (callback) URI registered in its console. The admin shows it,
read-only, under each provider group. The format is:

```
https://<store-domain>/sociallogin/account/callback/provider/<provider>/
```

For example `https://shop.example.com/sociallogin/account/callback/provider/google/`.

The URI is built from the secure base URL of the store view (`web/secure/base_url`), so it must
match exactly what the browser uses, including `https`, `www` and the trailing slash. With store
codes in URLs, each store view has a different URI: register all of them.

## Button icons

The buttons use the bundled icons `Digitalway_SocialLogin::images/<provider>.svg`. To replace one
(e.g. a monochrome icon for your theme), set the `icons` argument of `ButtonsProvider` in your own
module's `etc/frontend/di.xml`:

```xml
<type name="Digitalway\SocialLogin\Model\ButtonsProvider">
    <arguments>
        <argument name="icons" xsi:type="array">
            <item name="google" xsi:type="string">Vendor_Theme::images/google-mono.svg</item>
            <item name="facebook" xsi:type="string">https://cdn.example.com/icons/facebook.svg</item>
        </argument>
    </arguments>
</type>
```

The value is either a view asset ID (`Vendor_Module::path/to/icon.svg`, resolved with the current
theme fallback) or an absolute URL (`https://...` or `//...`). An icon set here takes precedence
over the icon supplied by the provider itself.
