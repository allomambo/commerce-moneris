# Local development

Use this guide when you are developing or debugging this plugin against a Craft project via a local checkout, instead of installing a released package.

## Composer path repository

Point your Craft project’s Composer config at the local plugin directory:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../_plugins/commerce-moneris"
        }
    ]
}
```

Then require the package (Composer will symlink the path repository when possible):

```bash
composer require allomambo/commerce-moneris:@dev
```

Adjust the `url` to match where you keep the plugin relative to the Craft project.

## DDEV

If the plugin lives outside the DDEV project directory, mount it into the web container. For example, create `config/docker-compose.plugin-mount.yml` in the Craft project:

```yaml
services:
  web:
    volumes:
      - "$HOME/Sites/_plugins:/var/www/html/_plugins"
```

Use a path repository URL that matches the mount inside the container:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/var/www/html/_plugins/commerce-moneris"
        }
    ]
}
```

Restart DDEV after adding the mount (`ddev restart`), then run `composer update allomambo/commerce-moneris` (or `composer require`) as needed.

## After linking

Install or enable the plugin in **Settings** → **Plugins** if it is not already installed, then configure the Moneris gateway as described in the [README](../README.md#configuration).
