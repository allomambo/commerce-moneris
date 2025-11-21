# Moneris Payment Gateway for Craft Commerce

A Craft CMS plugin that provides a payment gateway integration for Moneris using the Moneris Gateway API PHP library.

## Installation

### Using Composer (Path Repository)

This plugin is configured to be installed via a path repository in your Craft CMS project's `composer.json`.

1. The path repository is already configured in your project's `composer.json`:
   ```json
   {
       "repositories": [
           {
               "type": "path",
               "url": "../_plugins/moneris"
           }
       ]
   }
   ```

2. Install the plugin via Composer:
   ```bash
   composer require moneris/moneris
   ```

3. Install the plugin in Craft CMS:
   - Go to **Settings** → **Plugins** in the Craft CMS control panel
   - Find "Moneris" in the plugin list
   - Click "Install"

### DDEV Setup

If you're using DDEV and the plugin is located outside your project directory, you may need to configure a mount point. Create `config/docker-compose.plugin-mount.yml`:

```yaml
services:
  web:
    volumes:
      - "$HOME/Sites/diabete-drummond/_plugins:/var/www/html/_plugins"
```

Then update the path repository URL in `composer.json` to use the absolute path:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/var/www/html/_plugins/moneris"
        }
    ]
}
```

## Configuration

1. Go to **Commerce** → **Settings** → **Gateways**
2. Click "New Gateway"
3. Select "Moneris" from the gateway type dropdown
4. Configure the gateway settings:
   - **Store ID**: Your Moneris Store ID (can use environment variables)
   - **API Token**: Your Moneris API Token (can use environment variables)
   - **Environment**: Select "Staging" or "Production"
   - **Enable AVS**: Enable Address Verification System
   - **Enable CVD**: Enable Card Verification Digit

### Environment Variables

You can use Craft CMS's environment variable autosuggest feature to set the Store ID and API Token. Simply type `$` in the field and select from available environment variables, or type the variable name directly (e.g., `$MONERIS_STORE_ID`).

## Features

- **Purchase**: Direct payment processing
- **Authorize**: Pre-authorize payments
- **Capture**: Capture authorized payments
- **Refund**: Full and partial refunds
- **AVS Support**: Address Verification System
- **CVD Support**: Card Verification Digit

## Requirements

- Craft CMS 5.3.0 or later
- Craft Commerce 5.0.0 or later
- PHP 8.2 or later
- `allomambo/moneris-gateway-api-php` Composer package

## License

Proprietary

